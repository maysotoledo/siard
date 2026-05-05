<?php

namespace App\Enums;

enum FuncaoOperacional: string
{
    case IPC_EXPEDIENTE = 'IPC_EXPEDIENTE';
    case IPC_PLANTAO = 'IPC_PLANTAO';
    case EPC_EXPEDIENTE = 'EPC_EXPEDIENTE';
    case EPC_PLANTAO = 'EPC_PLANTAO';
    case CARTORIO_CENTRAL = 'CARTORIO_CENTRAL';
    case DPC = 'DPC';

    public function label(): string
    {
        return match ($this) {
            self::IPC_EXPEDIENTE => 'IPC expediente',
            self::IPC_PLANTAO => 'IPC plantão',
            self::EPC_EXPEDIENTE => 'EPC expediente',
            self::EPC_PLANTAO => 'EPC plantão',
            self::CARTORIO_CENTRAL => 'Cartório central',
            self::DPC => 'DPC',
        };
    }

    public function grupoOperacional(): string
    {
        return match ($this) {
            self::IPC_PLANTAO, self::EPC_PLANTAO => 'plantao',
            default => 'expediente',
        };
    }

    public function role(): string
    {
        return match ($this) {
            self::IPC_EXPEDIENTE => 'ipc',
            self::IPC_PLANTAO => 'ipc_plantao',
            self::EPC_EXPEDIENTE => 'epc',
            self::EPC_PLANTAO => 'epc_plantao',
            self::CARTORIO_CENTRAL => 'cartorio_central',
            self::DPC => 'dpc',
        };
    }

    public function podeSerCobertaPor(): array
    {
        return match ($this) {
            self::IPC_PLANTAO => [self::IPC_EXPEDIENTE],
            self::EPC_PLANTAO => [self::EPC_EXPEDIENTE],
            default => [],
        };
    }

    public static function fromRole(?string $role): ?self
    {
        return match ($role) {
            'ipc' => self::IPC_EXPEDIENTE,
            'ipc_plantao' => self::IPC_PLANTAO,
            'epc' => self::EPC_EXPEDIENTE,
            'epc_plantao' => self::EPC_PLANTAO,
            'cartorio_central' => self::CARTORIO_CENTRAL,
            'dpc' => self::DPC,
            default => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $funcao): array => [$funcao->value => $funcao->label()])
            ->all();
    }
}
