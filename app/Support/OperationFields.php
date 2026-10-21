<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class OperationFields
{
    public static function field(string $name, string $label, string $type = 'text', bool $required = true, array $extra = []): array
    {
        return compact('name', 'label', 'type', 'required') + $extra;
    }

    public static function normalize(array $fields, array $input, string $timezone = 'Asia/Jakarta', string $prefix = ''): array
    {
        $out = [];
        foreach ($fields as $field) {
            $key = $field['name'];
            $value = $input[$key] ?? null;
            $path = $prefix.$key;
            if ($field['type'] === 'file') {
                continue;
            }
            if (in_array($field['type'], ['repeat', 'map'])) {
                if ($value !== null && (! is_array($value) || count($value) > ($field['max'] ?? 100))) {
                    throw ValidationException::withMessages([$path => 'Daftar melebihi batas atau tidak valid.']);
                }
                $rows = [];
                foreach ($value ?? [] as $i => $row) {
                    if (! is_array($row)) {
                        throw ValidationException::withMessages([$path => 'Isian baris tidak valid.']);
                    }
                    $item = self::normalize($field['fields'], $row, $timezone, $path.'.'.$i.'.');
                    if ($field['type'] === 'map') {
                        if (! isset($item['key'], $item['value']) || isset($rows[$item['key']])) {
                            throw ValidationException::withMessages([$path => 'Lengkapi pasangan kolom/kunci dan nilai tanpa duplikasi.']);
                        }
                        $rows[$item['key']] = $item['value'];
                    } else {
                        $rows[] = $item;
                    }
                }
                $out[$key] = $rows;

                continue;
            }
            if ($field['type'] === 'group') {
                if ($value !== null && ! is_array($value)) {
                    throw ValidationException::withMessages([$path => 'Kelompok isian tidak valid.']);
                }
                $group = self::normalize($field['fields'], $value ?? [], $timezone, $path.'.');
                $meaningful = fn ($items) => array_filter($items, fn ($item) => is_array($item) ? count(array_filter($item, fn ($v) => $v !== [] && $v !== null && $v !== '')) > 0 : $item !== null && $item !== '');
                if ($meaningful($group) || $field['required']) {
                    $out[$key] = $group;
                }

                continue;
            }
            if (in_array($field['type'], ['lines', 'multi'])) {
                if ($field['type'] === 'lines') {
                    if (is_array($value)) {
                        throw ValidationException::withMessages([$path => 'Masukkan satu nilai pada setiap baris.']);
                    }
                    $value = preg_split('/\R/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
                }
                if (! is_array($value ?? [])) {
                    throw ValidationException::withMessages([$path => 'Pilihan tidak valid.']);
                }
                $values = array_values($value ?? []);
                if (array_filter($values, fn ($item) => ! is_scalar($item))) {
                    throw ValidationException::withMessages([$path => 'Pilihan tidak valid.']);
                }
                $out[$key] = isset($field['source']) ? array_map(fn ($item) => ctype_digit((string) $item) ? (int) $item : $item, $values) : $values;

                continue;
            }
            if ($value === null || $value === '') {
                if (isset($field['fixed'])) {
                    $out[$key] = $field['fixed'];
                }

                continue;
            }
            if (! is_scalar($value)) {
                throw ValidationException::withMessages([$path => 'Isian harus berupa satu nilai.']);
            }
            if ($field['type'] === 'money') {
                if (! preg_match('/^(-?)([0-9]{1,13})(?:\.([0-9]{1,2}))?$/D', (string) $value, $parts)) {
                    throw ValidationException::withMessages([$path => 'Gunakan nominal rupiah tanpa pemisah ribuan, maksimal dua angka desimal dengan titik.']);
                }
                $value = ((int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0')) * ($parts[1] === '-' ? -1 : 1);
            } elseif ($field['type'] === 'instant') {
                try {
                    $local = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', (string) $value, $timezone);
                    if ($local->format('Y-m-d\TH:i') !== $value) {
                        throw new \InvalidArgumentException;
                    }
                    $value = $local->toIso8601String();
                } catch (\Throwable) {
                    throw ValidationException::withMessages([$path => 'Waktu lokal tidak valid.']);
                }
            } elseif ($field['type'] === 'boolean') {
                if (! in_array((string) $value, ['0', '1'], true)) {
                    throw ValidationException::withMessages([$path => 'Pilih Ya atau Tidak.']);
                }
                $value = (string) $value === '1';
            } elseif (in_array($field['type'], ['lookup', 'number', 'version', 'employee-version', 'profile-version']) && preg_match('/^-?[0-9]{1,15}$/D', (string) $value)) {
                $value = (int) $value;
            }
            $out[$key] = $field['fixed'] ?? $value;
        }

        return $out;
    }
}
