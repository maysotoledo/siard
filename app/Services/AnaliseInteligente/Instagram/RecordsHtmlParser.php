<?php

namespace App\Services\AnaliseInteligente\Instagram;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class RecordsHtmlParser
{
    public function parse(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $libxmlErrors = libxml_get_errors();
        libxml_clear_errors();

        $fatalErrors = array_filter($libxmlErrors, fn ($e) => $e->level >= LIBXML_ERR_FATAL);
        if (! empty($fatalErrors)) {
            Log::warning('Instagram HTML: erros fatais de parsing', [
                'count' => count($fatalErrors),
                'first' => $fatalErrors[array_key_first($fatalErrors)]->message ?? null,
            ]);
        }

        $xp = new DOMXPath($dom);

        $target = $this->getSimpleValueByLabel($xp, 'Target');
        $generated = $this->getSimpleValueByLabel($xp, 'Generated');
        $dateRange = $this->getSimpleValueByLabel($xp, 'Date Range');
        $accountIdentifier = $this->getSimpleValueByLabel($xp, 'Account Identifier');
        $registrationDate = $this->getSimpleValueByLabel($xp, 'Registration Date');

        $vanityName = $this->extractVanityName($xp);

        $target = $target ?? $accountIdentifier;

        $registrationIpRaw = $this->extractRegistrationIpRaw($dom, $xp);
        $registrationParsed = $this->parseIpAndPort($registrationIpRaw);
        $registrationIp = $registrationParsed['ip'];

        $firstName = $this->extractFirstName($xp);
        $phone = $this->extractPhoneInfo($xp);
        $lastLocation = $this->extractLastLocation($dom);

        $ipEvents = $this->extractIpEvents($xp);

        $directThreads = $this->extractUnifiedMessagesThreadsDom($xp, $vanityName, $accountIdentifier);
        $followers = $this->extractRelationshipNames($xp, 'followers');
        $following = $this->extractRelationshipNames($xp, 'following');

        [$rangeStartUtc, $rangeEndUtc] = $this->parseDateRangeUtc($dateRange);

        $parseStats = [
            'ip_events_count' => count($ipEvents),
            'direct_threads_count' => count($directThreads),
            'followers_count' => count($followers),
            'following_count' => count($following),
            'has_target' => $target !== null,
            'has_registration_ip' => $registrationIp !== null,
            'has_registration_phone' => $phone['phone'] !== null,
            'has_last_location' => $lastLocation['latitude'] !== null,
            'has_date_range' => $dateRange !== null,
            'libxml_fatal_errors' => count($fatalErrors),
        ];

        return [
            'target' => $target,
            'generated_at' => $this->parseUtc($generated),
            'date_range' => $dateRange,
            'range_start_utc' => $rangeStartUtc,
            'range_end_utc' => $rangeEndUtc,

            'account_identifier' => $accountIdentifier,
            'vanity_name' => $vanityName,

            'first_name' => $firstName,
            'registration_date' => $this->parseUtc($registrationDate),
            'registration_ip' => $registrationIp,

            'registration_phone' => $phone['phone'],
            'registration_phone_verified_on' => $this->parseUtc($phone['verified_on']),

            'last_location_time' => $lastLocation['time'],
            'last_location_latitude' => $lastLocation['latitude'],
            'last_location_longitude' => $lastLocation['longitude'],
            'last_location_maps_url' => $this->makeMapsUrl(
                $lastLocation['latitude'],
                $lastLocation['longitude']
            ),

            'ip_events' => $ipEvents,

            'direct_threads' => $directThreads,
            'followers' => $followers,
            'following' => $following,

            '_parse_stats' => $parseStats,
        ];
    }

    private function getSimpleValueByLabel(DOMXPath $xp, string $label): ?string
    {
        $queries = [
            "//div[contains(@class,'t i')][normalize-space(text())='{$label}']/div[contains(@class,'m')]/div",
            "//div[contains(@class,'t') and contains(@class,'i')][normalize-space(text())='{$label}']/div[contains(@class,'m')]/div",
            "//*[@data-label='{$label}']/following-sibling::*[1]",
        ];

        foreach ($queries as $query) {
            $nodes = @$xp->query($query);
            if ($nodes && $nodes->length > 0) {
                $value = trim($nodes->item(0)?->textContent ?? '');
                if ($value !== '') return $value;
            }
        }

        return null;
    }

    private function extractVanityName(DOMXPath $xp): ?string
    {
        $nodes = $xp->query("//div[contains(@class,'t i')][normalize-space(text())='Vanity Name']/div[contains(@class,'m')]/div");
        if (! $nodes || $nodes->length === 0) return null;

        $value = trim($nodes->item(0)?->textContent ?? '');
        return $value !== '' ? $value : null;
    }

    private function extractFirstName(DOMXPath $xp): ?string
    {
        $nodes = $xp->query("//div[contains(@class,'t i')][normalize-space(text())='First']/div[contains(@class,'m')]/div");
        if (! $nodes || $nodes->length === 0) return null;

        $value = trim($nodes->item(0)?->textContent ?? '');
        return $value !== '' ? $value : null;
    }

    private function extractPhoneInfo(DOMXPath $xp): array
    {
        $value = $this->getSimpleValueByLabel($xp, 'Phone Numbers');

        if (! $value) {
            return ['phone' => null, 'verified_on' => null];
        }

        $phone = null;
        $verifiedOn = null;

        if (preg_match('/(\+?\d{10,16})/', $value, $m)) {
            $phone = $m[1];
        }

        if (preg_match('/Verified on\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+UTC)/i', $value, $m)) {
            $verifiedOn = $m[1];
        }

        return ['phone' => $phone, 'verified_on' => $verifiedOn];
    }

    private function extractRegistrationIpRaw(DOMDocument $dom, DOMXPath $xp): ?string
    {
        $value = $this->getSimpleValueByLabel($xp, 'Registration Ip')
            ?? $this->getSimpleValueByLabel($xp, 'Registration IP');

        if ($value) {
            return $value;
        }

        $sectionHtml = $this->extractPropertySectionHtml($dom, 'property-registration_ip');
        if ($sectionHtml === '') {
            return null;
        }

        $sectionHtml = preg_replace('/<div[^>]*class="[^"]*pageBreak[^"]*"[^>]*>.*?<\/div>/is', ' ', $sectionHtml) ?? $sectionHtml;
        $text = html_entity_decode(strip_tags($sectionHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return $this->extractFirstIpLikeValue($text);
    }

    private function extractFirstIpLikeValue(string $text): ?string
    {
        $definitionEnd = stripos($text, 'Registration Ip');
        if ($definitionEnd !== false) {
            $text = substr($text, $definitionEnd + strlen('Registration Ip'));
        }

        if (preg_match('/\[([0-9a-f:]+)\](?::\d{1,5})?/i', $text, $m)) {
            return $m[0];
        }

        if (preg_match('/\b(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\.(?:25[0-5]|2[0-4]\d|1?\d?\d)){3}(?::\d{1,5})?\b/', $text, $m)) {
            return $m[0];
        }

        if (preg_match('/\b[0-9a-f:]{2,}\b/i', $text, $m) && str_contains($m[0], ':') && filter_var($m[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $m[0];
        }

        return null;
    }

    private function extractLastLocation(DOMDocument $dom): array
    {
        $html = $this->extractPropertySectionHtml($dom, 'property-last_location');

        if ($html === '') {
            return ['time' => null, 'latitude' => null, 'longitude' => null];
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        $time = null;
        $lat = null;
        $lng = null;

        if (preg_match('/Time\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/i', $text, $m)) {
            $time = $this->parseUtc($m[1] . ' UTC');
        }

        preg_match_all('/-?\d+\.\d+/', $text, $matches);
        $numbers = $matches[0] ?? [];

        if (count($numbers) >= 2) {
            $coords = array_slice($numbers, -2);
            $lat = (float) $coords[0];
            $lng = (float) $coords[1];
        }

        return ['time' => $time, 'latitude' => $lat, 'longitude' => $lng];
    }

    private function extractPropertySectionHtml(DOMDocument $dom, string $propertyId): string
    {
        $target = $dom->getElementById($propertyId);
        if (! $target) return '';

        $html = '';
        $node = $target;

        while ($node) {
            if ($node instanceof DOMElement) {
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

    private function makeMapsUrl(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) return null;
        return "https://www.google.com/maps?q={$lat},{$lng}";
    }

    private function extractIpEvents(DOMXPath $xp): array
    {
        $container = $xp->query("//div[@id='property-ip_addresses']")->item(0);
        if (! $container) return [];

        $labels = $xp->query(
            ".//div[contains(@class,'t i')][normalize-space(text())='IP Address' or normalize-space(text())='Time']",
            $container
        );

        $events = [];
        $last = null;

        foreach ($labels as $labelNode) {
            $label = trim($labelNode->childNodes->item(0)?->textContent ?? $labelNode->textContent ?? '');

            $valueNode = null;
            foreach ($labelNode->childNodes as $child) {
                if ($child instanceof DOMElement && str_contains($child->getAttribute('class'), 'm')) {
                    $valueNode = $child->getElementsByTagName('div')->item(0);
                    break;
                }
            }

            $value = trim($valueNode?->textContent ?? '');

            if ($label === 'IP Address') {
                $parsed = $this->parseIpAndPort($value);
                $last = $parsed['ip'] ? $parsed : null;
            }

            if ($label === 'Time') {
                $time = $this->parseUtc($value);
                if ($last && $time instanceof Carbon) {
                    $events[] = [
                        'ip' => $last['ip'],
                        'ip_with_port' => $last['ip_with_port'],
                        'port' => $last['port'],
                        'time_utc' => $time->copy(),
                    ];
                }
            }
        }

        return $events;
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
            return ['ip' => $ipBase, 'ip_with_port' => "[{$ipBase}]:{$port}", 'port' => $port];
        }

        if (preg_match('/^\[([0-9a-fA-F:]+)\]$/', $value, $m)) {
            $ipBase = trim($m[1]);
            return ['ip' => $ipBase, 'ip_with_port' => "[{$ipBase}]", 'port' => null];
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d{1,5})$/', $value, $m)) {
            $ipBase = trim($m[1]);
            $port = (int) $m[2];
            return ['ip' => $ipBase, 'ip_with_port' => "{$ipBase}:{$port}", 'port' => $port];
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})$/', $value, $m)) {
            $ipBase = trim($m[1]);
            return ['ip' => $ipBase, 'ip_with_port' => $ipBase, 'port' => null];
        }

        $ipBase = $value;
        if (preg_match('/^\[([^\]]+)\]$/', $value, $m)) {
            $ipBase = trim($m[1]);
        }

        return ['ip' => $ipBase !== '' ? $ipBase : null, 'ip_with_port' => $value, 'port' => null];
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

    private function parseDateRangeUtc(?string $range): array
    {
        $range = trim((string) $range);
        if ($range === '' || ! str_contains($range, ' to ')) return [null, null];

        [$a, $b] = explode(' to ', $range, 2);

        return [$this->parseUtc($a), $this->parseUtc($b)];
    }

    private function extractRelationshipNames(DOMXPath $xp, string $type, int $limit = 5000): array
    {
        $sections = $this->findRelationshipSections($xp, $type);
        $names = [];

        foreach ($sections as $section) {
            if (! ($section instanceof DOMElement)) {
                continue;
            }

            $labelNodes = $xp->query(".//div[contains(@class,'t') and contains(@class,'i')]", $section);

            foreach ($labelNodes ?: [] as $labelNode) {
                if (! ($labelNode instanceof DOMElement)) {
                    continue;
                }

                $label = mb_strtolower($this->readLabel($labelNode));
                $value = $this->readValue($xp, $labelNode);

                if ($value === '') {
                    continue;
                }

                if ($this->isRelationshipNameLabel($label, $type) || preg_match('/\(Instagram:\s*\d+\)/i', $value)) {
                    foreach ($this->extractNamesFromRelationshipValue($value) as $name) {
                        if (count($names) >= $limit) break 2;
                        $names[$this->normalizeRelationshipNameKey($name)] = $name;
                    }
                }
            }

            if (! $labelNodes || $labelNodes->length === 0) {
                foreach ($this->extractInstagramNamesFromText($section->textContent ?? '') as $name) {
                    $names[$this->normalizeRelationshipNameKey($name)] = $name;
                }
            }
        }

        $out = array_values(array_filter($names, fn ($name) => trim((string) $name) !== ''));
        natcasesort($out);

        return array_values($out);
    }

    private function findRelationshipSections(DOMXPath $xp, string $type): array
    {
        $needleIds = $type === 'followers'
            ? ['follower', 'followers']
            : ['following', 'followings', 'followed', 'follows'];

        $sections = [];

        foreach ($needleIds as $needle) {
            $nodes = $xp->query(
                "//div[starts-with(@id,'property-') and contains(translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'{$needle}')]"
            );

            foreach ($nodes ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $sections[spl_object_id($node)] = $node;
                }
            }
        }

        $labelNeedles = $type === 'followers'
            ? ['Followers', 'Follower', 'Seguidores', 'Seguidor']
            : ['Following', 'Followings', 'Followed', 'Seguindo'];

        foreach ($labelNeedles as $label) {
            $nodes = $xp->query("//div[contains(@class,'t') and contains(@class,'i')][normalize-space(text())='{$label}']");

            foreach ($nodes ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $section = $this->nearestPropertySection($node);
                    if ($section instanceof DOMElement) {
                        $sections[spl_object_id($section)] = $section;
                    }
                }
            }
        }

        return array_values($sections);
    }

    private function nearestPropertySection(DOMElement $node): ?DOMElement
    {
        $current = $node;

        while ($current instanceof DOMElement) {
            $id = $current->getAttribute('id');

            if ($id !== '' && str_starts_with($id, 'property-')) {
                return $current;
            }

            $current = $current->parentNode instanceof DOMElement ? $current->parentNode : null;
        }

        return null;
    }

    private function isRelationshipNameLabel(string $label, string $type): bool
    {
        $labels = [
            'name',
            'full name',
            'username',
            'user name',
            'screen name',
            'instagram username',
            'vanity name',
            'profile',
            'account',
            'conta',
            'nome',
            'usuario',
            'usuário',
        ];

        if ($type === 'followers') {
            $labels[] = 'follower';
            $labels[] = 'followers';
            $labels[] = 'seguidor';
            $labels[] = 'seguidores';
        } else {
            $labels[] = 'following';
            $labels[] = 'followings';
            $labels[] = 'followed';
            $labels[] = 'seguindo';
        }

        return in_array($label, $labels, true);
    }

    private function extractNamesFromRelationshipValue(string $value): array
    {
        $names = $this->extractInstagramNamesFromText($value);

        if (count($names) > 0) {
            return $names;
        }

        $lines = preg_split("/\r\n|\r|\n/u", $value) ?: [$value];
        $out = [];

        foreach ($lines as $line) {
            $line = $this->cleanRelationshipName($line);

            if ($this->looksLikeRelationshipName($line)) {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function extractInstagramNamesFromText(string $text): array
    {
        preg_match_all('/([^\r\n]+?)\s*\(Instagram:\s*\d+\)\s*/iu', $text, $matches);
        $rawNames = $matches[1] ?? [];
        $names = [];

        foreach ($rawNames as $rawName) {
            $name = $this->cleanRelationshipName($rawName);

            if ($this->looksLikeRelationshipName($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function looksLikeRelationshipName(string $value): bool
    {
        if ($value === '' || mb_strlen($value) > 120) {
            return false;
        }

        if (preg_match('/^\d+$/', $value)) {
            return false;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}(?:\s+UTC)?$/', $value)) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    private function normalizeRelationshipNameKey(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }

    private function cleanRelationshipName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = preg_replace('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}(?:\s+UTC)?/u', ' ', $name) ?? $name;
        $name = preg_replace('/\b(?:followed on|follow time|timestamp|created at|updated at|time|date|data)\b/iu', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        do {
            $previous = $name;
            $name = trim(preg_replace(
                '/^(followers?|followings?|following|followed|seguidores?|seguindo|name|full name|username|user name|profile|account|nome|conta|usu[aá]rio)\s+/iu',
                '',
                $name
            ) ?? $name);
        } while ($name !== $previous);

        return $name;
    }

    // =========================================================
    // ✅ DIRECT
    // =========================================================
    private function extractUnifiedMessagesThreadsDom(DOMXPath $xp, ?string $vanityName, ?string $accountIdentifier): array
    {
        $container = $xp->query("//div[@id='property-unified_messages']")->item(0);
        if (! $container) return [];

        $threadOuters = $xp->query(".//div[contains(@class,'t') and contains(@class,'o')][.//div[contains(@class,'t') and contains(@class,'i')][normalize-space(text())='Thread']]", $container);
        if (! $threadOuters || $threadOuters->length === 0) return [];

        $meVanity = trim((string) $vanityName);
        $meId = trim((string) $accountIdentifier);

        $threads = [];

        foreach ($threadOuters as $outer) {
            if (!($outer instanceof DOMElement)) continue;

            $labelNodes = $xp->query(".//div[contains(@class,'t') and contains(@class,'i')]", $outer);
            if (! $labelNodes || $labelNodes->length === 0) continue;

            $participantsRaw = [];
            $messages = [];

            $currentAuthorRaw = null;
            $currentSent = null;

            foreach ($labelNodes as $labelNode) {
                if (!($labelNode instanceof DOMElement)) continue;

                $label = $this->readLabel($labelNode);
                if ($label === '') continue;

                $value = $this->readValue($xp, $labelNode);

                if ($label === 'Current Participants') {
                    $participantsRaw = $this->extractParticipantsFromCurrentParticipantsValue($value);
                    continue;
                }

                if ($label === 'Author') {
                    $currentAuthorRaw = $value;
                    continue;
                }

                if ($label === 'Sent') {
                    $currentSent = $value;
                    continue;
                }

                if ($label === 'Body') {
                    if ($currentAuthorRaw && $currentSent) {
                        $messages[] = [
                            'author' => $this->extractParticipantName($currentAuthorRaw),
                            'author_raw' => $currentAuthorRaw,
                            'sent_utc' => $currentSent,
                            'body' => $value,
                        ];
                    }

                    $currentAuthorRaw = null;
                    $currentSent = null;
                    continue;
                }
            }

            $other = $this->pickOtherParticipant($participantsRaw, $meVanity, $meId);

            // fallback se não achou: pega o primeiro autor que não seja o alvo
            if (! $other && count($messages) > 0) {
                foreach ($messages as $m) {
                    $a = trim((string) ($m['author'] ?? ''));
                    if ($meVanity !== '' && strcasecmp($a, $meVanity) === 0) continue;
                    $other = $a !== '' ? $a : null;
                    if ($other) break;
                }
            }

            if (! $other || count($messages) === 0) continue;

            $threads[] = [
                'participant' => $other,
                'messages' => $messages,
            ];
        }

        $grouped = [];
        foreach ($threads as $t) {
            $key = $t['participant'];
            $grouped[$key] ??= ['participant' => $t['participant'], 'messages' => []];
            $grouped[$key]['messages'] = array_merge($grouped[$key]['messages'], $t['messages']);
        }

        return array_values($grouped);
    }

    private function extractParticipantsFromCurrentParticipantsValue(string $value): array
    {
        // remove timestamp tipo "2025-07-15 10:39:55 UTC"
        $lines = preg_split("/\r\n|\r|\n/u", $value) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        $out = [];
        foreach ($lines as $l) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+UTC$/', $l)) {
                continue;
            }

            // mantém só "nome (Instagram: id)"
            if (preg_match('/^(.+?)\s*\(Instagram:\s*\d+\)\s*$/i', $l)) {
                $out[] = $l;
            }
        }

        return $out;
    }

    private function readLabel(DOMElement $labelNode): string
    {
        $label = '';

        foreach ($labelNode->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $label .= $child->textContent;
                continue;
            }

            if ($child instanceof DOMElement) {
                $class = $child->getAttribute('class');
                if (str_contains($class, 'm')) {
                    break;
                }
            }
        }

        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        return $label;
    }

    private function readValue(DOMXPath $xp, DOMElement $labelNode): string
    {
        $valueNode = $xp->query(".//div[contains(@class,'m')]/div", $labelNode)->item(0);
        return trim($valueNode?->textContent ?? '');
    }

    private function pickOtherParticipant(array $participantsRaw, string $meVanity, string $meId): ?string
    {
        foreach ($participantsRaw as $praw) {
            $pname = $this->extractParticipantName($praw);

            $isMe = false;
            if ($meVanity !== '' && strcasecmp($pname, $meVanity) === 0) $isMe = true;
            if ($meId !== '' && str_contains($praw, "(Instagram: {$meId})")) $isMe = true;

            if (! $isMe) return $pname;
        }

        return null;
    }

    private function extractParticipantName(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/^(.+?)\s*\(Instagram:\s*\d+\)\s*$/i', $raw, $m)) {
            return trim($m[1]);
        }

        return $raw;
    }
}
