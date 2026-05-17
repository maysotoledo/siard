<?php

namespace App\Services\AnaliseInteligente\Whatsapp;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RecordsHtmlParser
{
    public function parse(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($html);

        $libxmlErrors = libxml_get_errors();
        libxml_clear_errors();

        $criticalErrors = array_filter($libxmlErrors, fn (\LibXMLError $e) => $e->level === LIBXML_ERR_FATAL);
        if (count($criticalErrors) > 0) {
            Log::warning('RecordsHtmlParser: erros criticos no HTML do WhatsApp.', [
                'errors' => array_map(fn (\LibXMLError $e) => trim($e->message), array_values($criticalErrors)),
            ]);
        }

        $xp = new \DOMXPath($dom);

        $target = $this->getSimpleValueByLabel($xp, 'Target');
        $accountIdentifier = $this->getSimpleValueByLabel($xp, 'Account Identifier');
        $target = $target ?? $accountIdentifier;

        $generated = $this->getSimpleValueByLabel($xp, 'Generated');
        $dateRange = $this->getSimpleValueByLabel($xp, 'Date Range');

        $registeredEmailsRaw = $this->getSimpleValueByLabel($xp, 'Registered Email Addresses');
        $registeredEmails = [];
        if (is_string($registeredEmailsRaw) && trim($registeredEmailsRaw) !== '') {
            preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $registeredEmailsRaw, $m);
            $registeredEmails = array_values(array_unique($m[0] ?? []));
        }

        $deviceInfo = $this->extractDeviceInfo($xp);

        $symTotalFromLabel = $this->extractLeadingInt($this->getSimpleValueByLabel($xp, 'Symmetric contacts'));
        $asymTotalFromLabel = $this->extractLeadingInt($this->getSimpleValueByLabel($xp, 'Asymmetric contacts'));

        $contacts = $this->extractAddressBookContacts($dom, $symTotalFromLabel, $asymTotalFromLabel);
        $symmetricContacts = $contacts['symmetric_contacts'];
        $asymmetricContacts = $contacts['asymmetric_contacts'];

        $symTotal = $symTotalFromLabel ?? count($symmetricContacts);
        $asymTotal = $asymTotalFromLabel ?? count($asymmetricContacts);

        $ipEvents = $this->extractIpEvents($xp);

        $groups = $this->extractGroupsInfo($dom);

        $connectionInfo = $this->extractConnectionInfo($dom);

        $messageLog = $this->extractMessageLog($dom);

        [$rangeStartUtc, $rangeEndUtc] = $this->parseDateRangeUtc($dateRange);
        $generatedAtUtc = $this->parseUtc($generated);

        if ($target === null || trim((string) $target) === '') {
            Log::warning('RecordsHtmlParser: alvo (Target/Account Identifier) nao encontrado no HTML.');
        }

        if (count($ipEvents) === 0) {
            Log::warning('RecordsHtmlParser: nenhum IP event extraido do HTML.', [
                'target' => $target,
                'date_range' => $dateRange,
            ]);
        }

        return [
            'target' => $target,
            'account_identifier' => $accountIdentifier,

            'generated_at' => $generatedAtUtc,
            'date_range' => $dateRange,
            'range_start_utc' => $rangeStartUtc,
            'range_end_utc' => $rangeEndUtc,

            'device' => $deviceInfo['device'],
            'device_build' => $deviceInfo['device_build'],

            'registered_emails' => $registeredEmails,

            'symmetric_contacts_total' => $symTotal,
            'asymmetric_contacts_total' => $asymTotal,

            'symmetric_contacts_count' => $symTotal,
            'asymmetric_contacts_count' => $asymTotal,

            'symmetric_contacts' => $symmetricContacts,
            'asymmetric_contacts' => $asymmetricContacts,

            'ip_events' => $ipEvents,

            'groups' => $groups,
            'connection_info' => $connectionInfo,

            'message_log' => $messageLog,
        ];
    }

    public function parseBilhetagemOnly(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($html);

        return $this->extractMessageLog($dom);
    }

    private function getSimpleValueByLabel(\DOMXPath $xp, string $label): ?string
    {
        $query = "//div[contains(@class,'t i')][normalize-space(text())='{$label}']/div[contains(@class,'m')]/div";
        $nodes = $xp->query($query);

        if (! $nodes || $nodes->length === 0) return null;

        $value = trim($nodes->item(0)?->textContent ?? '');

        return $value !== '' ? $value : null;
    }

    private function extractDeviceInfo(\DOMXPath $xp): array
    {
        $manufacturer = null;
        $model = null;
        $deviceBuild = null;

        $deviceInfoBlocks = $xp->query("//div[contains(@class,'t i')][normalize-space(text())='Device Info']/div[contains(@class,'m')]");

        if ($deviceInfoBlocks && $deviceInfoBlocks->length > 0) {
            $container = $deviceInfoBlocks->item(0);
            $rows = $xp->query(".//div[contains(@class,'t o')]", $container);

            if ($rows) {
                foreach ($rows as $row) {
                    $labelNode = $xp->query(".//div[contains(@class,'t i')]", $row)?->item(0);
                    if (! $labelNode) continue;

                    $labelText = trim($labelNode->childNodes->item(0)?->textContent ?? $labelNode->textContent ?? '');

                    $valueNode = null;
                    foreach ($labelNode->childNodes as $child) {
                        if ($child instanceof \DOMElement && str_contains($child->getAttribute('class'), 'm')) {
                            $valueNode = $child->getElementsByTagName('div')->item(0);
                            break;
                        }
                    }

                    $valueText = trim($valueNode?->textContent ?? '');
                    if ($valueText === '') continue;

                    if (strcasecmp($labelText, 'Device Manufacturer') === 0) $manufacturer = $valueText;
                    if (strcasecmp($labelText, 'Device Model') === 0) $model = $valueText;

                    if (
                        strcasecmp($labelText, 'OS Version') === 0 ||
                        strcasecmp($labelText, 'Device OS Build Number') === 0
                    ) {
                        $deviceBuild = $valueText;
                    }
                }
            }
        }

        $deviceBuild ??= $this->getSimpleValueByLabel($xp, 'Device OS Build Number');

        $device = null;
        if ($manufacturer && $model) $device = "{$manufacturer} - {$model}";
        elseif ($model) $device = $model;
        elseif ($manufacturer) $device = $manufacturer;

        return [
            'device' => $device,
            'device_build' => $deviceBuild,
        ];
    }

    private function extractAddressBookContacts(\DOMDocument $dom, ?int $symCount, ?int $asymCount): array
    {
        $sectionHtml = $this->extractPropertySectionHtml($dom, 'property-address_book_info');

        if ($sectionHtml === '') {
            return ['symmetric_contacts' => [], 'asymmetric_contacts' => []];
        }

        $sectionHtml = preg_replace('/<div[^>]*class="p"[^>]*>\s*<\/div>/i', "\n", $sectionHtml) ?? $sectionHtml;
        $sectionHtml = preg_replace('/<br\s*\/?>/i', "\n", $sectionHtml) ?? $sectionHtml;

        $text = html_entity_decode(strip_tags($sectionHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\r]+/', ' ', $text) ?? '';
        $text = preg_replace("/\n+/", "\n", $text) ?? '';
        $text = trim($text);

        $startPos = stripos($text, 'Symmetric contacts');
        if ($startPos !== false) {
            $text = substr($text, $startPos);
        }

        preg_match_all('/(?<!\d)(55\d{10,13})(?!\d)/', $text, $matches);
        $allPhones = array_values($matches[1] ?? []);

        if (count($allPhones) === 0) {
            preg_match_all('/\d{10,14}/', $text, $m2);
            $cands = array_values($m2[0] ?? []);
            $allPhones = array_values(array_filter(array_map(function ($n) {
                $n = preg_replace('/\D+/', '', (string) $n) ?? '';
                return str_starts_with($n, '55') ? $n : null;
            }, $cands)));
        }

        $symmetric = [];
        $asymmetric = [];

        if (($symCount ?? 0) > 0) $symmetric = array_slice($allPhones, 0, $symCount);
        if (($asymCount ?? 0) > 0) $asymmetric = array_slice($allPhones, $symCount ?? 0, $asymCount);

        return [
            'symmetric_contacts' => array_values(array_unique($symmetric)),
            'asymmetric_contacts' => array_values(array_unique($asymmetric)),
        ];
    }

    private function extractPropertySectionHtml(\DOMDocument $dom, string $propertyId): string
    {
        $target = $dom->getElementById($propertyId);

        if (! $target) {
            $xp = new \DOMXPath($dom);
            $node = $xp->query("//*[@id='{$propertyId}']")?->item(0);
            $target = $node instanceof \DOMElement ? $node : null;
        }

        if (! $target) return '';

        $html = '';
        $node = $target;

        while ($node) {
            if ($node instanceof \DOMElement) {
                $id = $node->getAttribute('id');

                if ($node !== $target && $id !== '' && str_starts_with($id, 'property-')) {
                    break;
                }

                $html .= $dom->saveHTML($node);
            }

            $node = $node->nextSibling;
        }

        return $html;
    }

    private function extractIpEvents(\DOMXPath $xp): array
    {
        $labels = $xp->query("//div[contains(@class,'t i')][normalize-space(text())='Time' or normalize-space(text())='IP Address']");

        $ipEvents = [];
        $last = null;

        if (! $labels) return $ipEvents;

        foreach ($labels as $labelNode) {
            $label = trim($labelNode->childNodes->item(0)?->textContent ?? $labelNode->textContent ?? '');

            $valueNode = null;
            foreach ($labelNode->childNodes as $child) {
                if ($child instanceof \DOMElement && str_contains($child->getAttribute('class'), 'm')) {
                    $valueNode = $child->getElementsByTagName('div')->item(0);
                    break;
                }
            }

            $value = trim($valueNode?->textContent ?? '');

            if ($label === 'Time') {
                $timeUtc = $this->parseUtc($value);

                if ($last && $timeUtc instanceof Carbon) {
                    $ipEvents[] = [
                        'ip' => $last['ip'],
                        'ip_with_port' => $last['ip_with_port'],
                        'port' => $last['port'],
                        'time_utc' => $timeUtc->copy(),
                    ];
                }
            }

            if ($label === 'IP Address') {
                $parsed = $this->parseIpAndPort($value);
                $last = $parsed['ip'] ? $parsed : null;
            }
        }

        return $ipEvents;
    }

    private function parseIpAndPort(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return ['ip' => null, 'ip_with_port' => null, 'port' => null];
        }

        if (preg_match('/^\[([0-9a-fA-F:]+)\]:(\d{1,5})$/', $value, $m)) {
            $ipBase = trim($m[1]);
            $port = (int) $m[2];

            return [
                'ip' => $ipBase,
                'ip_with_port' => "[{$ipBase}]:{$port}",
                'port' => $port,
            ];
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d{1,5})$/', $value, $m)) {
            $ipBase = trim($m[1]);
            $port = (int) $m[2];

            return [
                'ip' => $ipBase,
                'ip_with_port' => "{$ipBase}:{$port}",
                'port' => $port,
            ];
        }

        return [
            'ip' => $value,
            'ip_with_port' => $value,
            'port' => null,
        ];
    }

    private function parseUtc(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $value = str_replace(' UTC', '', $value);

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseUtcFlexible(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $value = str_replace(' UTC', '', $value);

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'UTC');
        } catch (\Throwable) {}

        try {
            return Carbon::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateRangeUtc(?string $range): array
    {
        $range = trim((string) $range);

        if ($range === '' || ! str_contains($range, ' to ')) {
            return [null, null];
        }

        [$a, $b] = explode(' to ', $range, 2);

        return [
            $this->parseUtc($a),
            $this->parseUtc($b),
        ];
    }

    private function extractLeadingInt(?string $text): ?int
    {
        $text = trim((string) $text);
        if ($text === '') return null;

        if (preg_match('/^(\d+)/', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function extractGroupsInfo(\DOMDocument $dom): array
    {
        $sectionHtml = $this->extractPropertySectionHtml($dom, 'property-groups_info');

        if ($sectionHtml === '') {
            return ['owned' => [], 'participating' => []];
        }

        $sectionHtml = preg_replace('/<div[^>]*class="p"[^>]*>\s*<\/div>/i', "\n", $sectionHtml) ?? $sectionHtml;
        $sectionHtml = preg_replace('/<br\s*\/?>/i', "\n", $sectionHtml) ?? $sectionHtml;

        $txt = html_entity_decode(strip_tags($sectionHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/[ \t\r]+/', ' ', $txt) ?? '';
        $txt = preg_replace("/\n+/", "\n", $txt) ?? '';
        $txt = trim($txt);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $txt)), fn ($l) => $l !== ''));

        $groups = ['owned' => [], 'participating' => []];
        $section = null;
        $current = null;

        $newGroup = fn () => [
            'id' => null,
            'creation_utc' => null,
            'size' => null,
            'description' => null,
            'subject' => null,
        ];

        $push = function () use (&$current, &$groups, &$section) {
            if (! $section || ! is_array($current)) {
                $current = null;
                return;
            }

            if (! empty($current['id'])) {
                $groups[$section][] = $current;
            }

            $current = null;
        };

        $rxId = '/\bID\s*([0-9]{10,})\b/u';
        $rxCreation = '/\bCreation\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})\s*UTC\b/iu';
        $rxSize = '/\bSize\s*([0-9]{1,6})\b/u';
        $rxSubject = '/\bSubject\s*(.+)$/iu';
        $rxDescription = '/\bDescription\s*(.+)$/iu';

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (stripos($line, 'Owned Groups') === 0) {
                $push();
                $section = 'owned';
                continue;
            }

            if (stripos($line, 'Participating Groups') === 0) {
                $push();
                $section = 'participating';
                continue;
            }

            if (! $section) continue;

            if (strcasecmp($line, 'ID') === 0) {
                $id = $lines[$i + 1] ?? null;
                if (is_string($id) && preg_match('/^[0-9]{10,}$/', $id)) {
                    $push();
                    $current = $newGroup();
                    $current['id'] = $id;
                    $i++;
                }
                continue;
            }

            if (strcasecmp($line, 'Creation') === 0) {
                if ($current) {
                    $v = $lines[$i + 1] ?? null;
                    $current['creation_utc'] = $this->parseUtc((string) $v);
                }
                $i++;
                continue;
            }

            if (strcasecmp($line, 'Size') === 0) {
                if ($current) {
                    $v = $lines[$i + 1] ?? null;
                    $n = preg_replace('/\D+/', '', (string) $v) ?? '';
                    $current['size'] = $n !== '' ? (int) $n : null;
                }
                $i++;
                continue;
            }

            if (strcasecmp($line, 'Subject') === 0) {
                if ($current) $current['subject'] = $lines[$i + 1] ?? null;
                $i++;
                continue;
            }

            if (strcasecmp($line, 'Description') === 0) {
                if ($current) $current['description'] = $lines[$i + 1] ?? null;
                $i++;
                continue;
            }

            if (preg_match($rxId, $line, $m)) {
                $push();
                $current = $newGroup();
                $current['id'] = $m[1];
            }

            if (! $current) continue;

            if (preg_match($rxCreation, $line, $m)) {
                $current['creation_utc'] = $this->parseUtc($m[1] . ' UTC');
            }

            if (preg_match($rxSize, $line, $m)) {
                $current['size'] = (int) $m[1];
            }

            if (preg_match($rxSubject, $line, $m)) {
                $val = trim((string) $m[1]);
                if ($val !== '') $current['subject'] = $val;
            }

            if (preg_match($rxDescription, $line, $m)) {
                $val = trim((string) $m[1]);
                if ($val !== '') $current['description'] = $val;
            }
        }

        $push();

        return $groups;
    }

    private function extractConnectionInfo(\DOMDocument $dom): array
    {
        $sectionHtml = $this->extractPropertySectionHtml($dom, 'property-connection_info');

        if ($sectionHtml === '') {
            $xp = new \DOMXPath($dom);
            $maybe = $xp->query("//div[contains(@class,'t i')][normalize-space(text())='Connection']")?->item(0);
            if ($maybe instanceof \DOMElement) {
                $parent = $maybe->parentNode instanceof \DOMElement ? $maybe->parentNode : null;
                if ($parent) {
                    $sectionHtml = $dom->saveHTML($parent) ?: '';
                }
            }
        }

        if (trim($sectionHtml) === '') return [];

        $sectionHtml = preg_replace('/<div[^>]*class="p"[^>]*>\s*<\/div>/i', "\n", $sectionHtml) ?? $sectionHtml;
        $sectionHtml = preg_replace('/<br\s*\/?>/i', "\n", $sectionHtml) ?? $sectionHtml;

        $txt = html_entity_decode(strip_tags($sectionHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/[ \t\r]+/', ' ', $txt) ?? '';
        $txt = preg_replace("/\n+/", "\n", $txt) ?? '';
        $txt = trim($txt);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $txt)), fn ($l) => $l !== ''));

        $out = [
            'device_id' => null,
            'service_start_utc' => null,
            'device_type' => null,
            'app_version' => null,
            'device_os_build_number' => null,
            'connection_state' => null,
            'last_seen_utc' => null,
            'last_ip' => null,
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $label = mb_strtolower(preg_replace('/\s+/u', ' ', $lines[$i]) ?? $lines[$i]);
            $next = $lines[$i + 1] ?? null;

            if ($label === 'device id' && $next) $out['device_id'] = $next;
            if ($label === 'service start' && $next) $out['service_start_utc'] = $this->parseUtcFlexible($next);
            if ($label === 'device type' && $next) $out['device_type'] = $next;
            if ($label === 'app version' && $next) $out['app_version'] = $next;
            if ($label === 'device os build number' && $next) $out['device_os_build_number'] = $next;
            if ($label === 'connection state' && $next) $out['connection_state'] = $next;
            if ($label === 'last seen' && $next) $out['last_seen_utc'] = $this->parseUtcFlexible($next);
            if ($label === 'last ip' && $next) $out['last_ip'] = $next;
        }

        if ($out['last_seen_utc'] === null) {
            if (preg_match('/Last\s*seen\s*([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})\s*UTC/i', $txt, $m)) {
                $out['last_seen_utc'] = $this->parseUtc($m[1] . ' UTC');
            }
        }

        if ($out['last_ip'] === null) {
            if (preg_match('/Last\s*IP\s*([^\s]+)/i', $txt, $m)) {
                $out['last_ip'] = trim($m[1]);
            }
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== '');
    }

    private function extractMessageLog(\DOMDocument $dom): array
    {
        $rows = $this->extractMessageLogByDom($dom);
        if (count($rows) > 0) return $rows;

        return $this->extractMessageLogByTextFallback($dom);
    }

    /**
     * ✅ FIX AQUI: SEMPRE retorna array (mesmo se nada encontrado)
     */
    private function extractMessageLogByDom(\DOMDocument $dom): array
    {
        $ids = [
            'property-message_log',
            'property-message_log_info',
            'property-messages_log',
            'property-billing_info',
            'property-bilhetagem',
        ];

        $sectionHtml = '';
        foreach ($ids as $id) {
            $sectionHtml = $this->extractPropertySectionHtml($dom, $id);
            if (trim($sectionHtml) !== '') break;
        }

        if (trim($sectionHtml) === '') return [];

        $tmp = new \DOMDocument();
        libxml_use_internal_errors(true);
        $tmp->loadHTML($sectionHtml);

        $xp = new \DOMXPath($tmp);

        $messageNodes = $xp->query("//div[contains(@class,'t i')][normalize-space(text())='Message']");
        if (! $messageNodes || $messageNodes->length === 0) return [];

        $out = [];

        foreach ($messageNodes as $mn) {
            $container = $mn->parentNode instanceof \DOMElement ? $mn->parentNode : null;
            if (! $container) continue;

            $fields = $this->extractLabeledFields($xp, $container);

            $timestamp = $this->cleanNullableString($fields['Timestamp'] ?? null);
            $messageId = $this->cleanNullableString($fields['Message Id'] ?? $fields['Message ID'] ?? null);
            $sender = $this->cleanNullableString($fields['Sender'] ?? null);

            $recipients = $this->cleanNullableString($fields['Recipients'] ?? $fields['Recipient'] ?? null);
            $recipientPhone = $this->extractFirstPhone($recipients);

            $senderIp = $this->cleanNullableString($fields['Sender Ip'] ?? $fields['Sender IP'] ?? null);
            $senderPort = $this->cleanNullableString($fields['Sender Port'] ?? null);
            $type = $this->cleanNullableString($fields['Type'] ?? null);

            if (! $recipientPhone) continue;

            $out[] = [
                'timestamp_utc' => $timestamp ? $this->parseUtcFlexible($timestamp) : null,
                'message_id' => $messageId,
                'sender' => $sender,
                'recipient' => $recipientPhone,
                'sender_ip' => $senderIp,
                'sender_port' => $senderPort !== null && $senderPort !== '' ? (int) preg_replace('/\D+/', '', $senderPort) : null,
                'type' => $type,
            ];
        }

        // ✅ GARANTIA: sempre retorna array
        return $out;
    }

    private function extractLabeledFields(\DOMXPath $xp, \DOMElement $container): array
    {
        $labels = $xp->query(".//div[contains(@class,'t i')]", $container);
        if (! $labels) return [];

        $fields = [];

        foreach ($labels as $labelNode) {
            $label = trim($labelNode->childNodes->item(0)?->textContent ?? $labelNode->textContent ?? '');
            if ($label === '') continue;

            $valNode = $this->extractValueContainer($labelNode);
            $val = trim($valNode?->textContent ?? '');

            if ($val !== '') {
                $fields[$label] = $val;
            }
        }

        return $fields;
    }

    private function extractValueContainer(\DOMElement $labelNode): ?\DOMElement
    {
        foreach ($labelNode->childNodes as $child) {
            if ($child instanceof \DOMElement && str_contains($child->getAttribute('class'), 'm')) {
                return $child->getElementsByTagName('div')->item(0) instanceof \DOMElement
                    ? $child->getElementsByTagName('div')->item(0)
                    : null;
            }
        }

        $parent = $labelNode->parentNode instanceof \DOMElement ? $labelNode->parentNode : null;
        if (! $parent) return null;

        foreach ($parent->childNodes as $sib) {
            if ($sib instanceof \DOMElement && str_contains($sib->getAttribute('class'), 'm')) {
                return $sib->getElementsByTagName('div')->item(0) instanceof \DOMElement
                    ? $sib->getElementsByTagName('div')->item(0)
                    : null;
            }
        }

        return null;
    }

    private function extractFirstPhone(?string $text): ?string
    {
        $text = (string) $text;
        if (trim($text) === '') return null;

        if (preg_match('/(?<!\d)(55\d{10,13})(?!\d)/', $text, $m)) {
            return $this->normalizePhone($m[1]);
        }

        if (preg_match('/\d{10,14}/', $text, $m2)) {
            $n = $this->normalizePhone($m2[0]);
            return $n ?: null;
        }

        return null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $n = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $n = trim($n);

        if ($n === '') return null;

        if (! str_starts_with($n, '55')) {
            if (strlen($n) >= 10 && strlen($n) <= 11) {
                $n = '55' . $n;
            }
        }

        if (! preg_match('/^55\d{10,13}$/', $n)) return null;

        return $n;
    }

    private function cleanNullableString(?string $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    private function extractMessageLogByTextFallback(\DOMDocument $dom): array
    {
        // (mantido igual ao seu)
        $ids = [
            'property-message_log',
            'property-message_log_info',
            'property-messages_log',
            'property-billing_info',
            'property-bilhetagem',
        ];

        $sectionHtml = '';
        foreach ($ids as $id) {
            $sectionHtml = $this->extractPropertySectionHtml($dom, $id);
            if (trim($sectionHtml) !== '') break;
        }

        if (trim($sectionHtml) === '') return [];

        $sectionHtml = preg_replace('/<div[^>]*class="p"[^>]*>\s*<\/div>/i', "\n", $sectionHtml) ?? $sectionHtml;
        $sectionHtml = preg_replace('/<br\s*\/?>/i', "\n", $sectionHtml) ?? $sectionHtml;

        $txt = html_entity_decode(strip_tags($sectionHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/[ \t\r]+/', ' ', $txt) ?? '';
        $txt = preg_replace("/\n+/", "\n", $txt) ?? '';
        $txt = trim($txt);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $txt)), fn ($l) => $l !== ''));

        $rows = [];
        $current = null;

        $newRow = fn () => [
            'timestamp_utc' => null,
            'message_id' => null,
            'sender' => null,
            'recipient' => null,
            'sender_ip' => null,
            'sender_port' => null,
            'type' => null,
        ];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $lc = mb_strtolower($line);

            if (in_array($lc, ['message', 'timestamp', 'message id', 'message id:'], true)) {
                if ($current && ! empty($current['recipient'])) $rows[] = $current;
                $current = $newRow();
            }

            if (! $current) continue;

            if ($lc === 'timestamp') {
                $v = $lines[$i + 1] ?? null;
                $current['timestamp_utc'] = $this->parseUtcFlexible((string) $v);
                $i++;
                continue;
            }

            if ($lc === 'message id' || $lc === 'message id:') {
                $v = $lines[$i + 1] ?? null;
                $current['message_id'] = $this->cleanNullableString((string) $v);
                $i++;
                continue;
            }

            if ($lc === 'sender') {
                $v = $lines[$i + 1] ?? null;
                $current['sender'] = $this->cleanNullableString((string) $v);
                $i++;
                continue;
            }

            if ($lc === 'recipients' || $lc === 'recipient') {
                $v = $lines[$i + 1] ?? null;
                $current['recipient'] = $this->extractFirstPhone((string) $v);
                $i++;
                continue;
            }

            if (in_array($lc, ['sender ip', 'sender ip:'], true)) {
                $v = $lines[$i + 1] ?? null;
                $current['sender_ip'] = $this->cleanNullableString((string) $v);
                $i++;
                continue;
            }

            if ($lc === 'sender port') {
                $v = $lines[$i + 1] ?? null;
                $v = $this->cleanNullableString((string) $v);
                $current['sender_port'] = $v !== null ? (int) preg_replace('/\D+/', '', $v) : null;
                $i++;
                continue;
            }

            if ($lc === 'type') {
                $v = $lines[$i + 1] ?? null;
                $current['type'] = $this->cleanNullableString((string) $v);
                $i++;
                continue;
            }
        }

        if ($current && ! empty($current['recipient'])) $rows[] = $current;

        return $rows;
    }
}
