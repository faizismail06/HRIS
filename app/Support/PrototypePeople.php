<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** UI fixtures only; no real identity, financial or medical records. */
class PrototypePeople
{
    public static function departments(): array
    {
        return [
            'direksi' => ['name' => 'Direksi', 'lead' => 'EMP-001', 'parent' => null],
            'sdm' => ['name' => 'Sumber Daya Manusia', 'lead' => 'EMP-002', 'parent' => 'direksi'],
            'operasional' => ['name' => 'Operasional', 'lead' => 'EMP-003', 'parent' => 'direksi'],
            'keuangan' => ['name' => 'Keuangan', 'lead' => 'EMP-001', 'parent' => 'direksi'],
            'teknologi' => ['name' => 'Teknologi', 'lead' => 'EMP-001', 'parent' => 'direksi'],
            'pemasaran' => ['name' => 'Pemasaran', 'lead' => 'EMP-001', 'parent' => 'direksi'],
        ];
    }

    public static function statuses(): array
    {
        return ['ACTIVE' => 'Aktif', 'PREBOARDING' => 'Pra-bergabung', 'OFFBOARDING' => 'Offboarding', 'ENDED' => 'Berakhir'];
    }

    public static function employees(): array
    {
        $rows = [
            ['EMP-001', 'Ratna Putri', 'Direktur', 'direksi', 'Jakarta', 'ACTIVE', '2019-02-04', null],
            ['EMP-002', 'Lestari Anindya', 'Head of People', 'sdm', 'Jakarta', 'ACTIVE', '2021-03-15', 'EMP-001'],
            ['EMP-003', 'Bima Prasetya', 'Head of Operations', 'operasional', 'Jakarta', 'ACTIVE', '2020-07-01', 'EMP-001'],
            ['EMP-004', 'Nadia Pramesti', 'Koordinator Operasional', 'operasional', 'Jakarta', 'ACTIVE', '2023-08-07', 'EMP-003'],
            ['EMP-005', 'Arif Wicaksono', 'Analis Keuangan', 'keuangan', 'Bandung', 'ACTIVE', '2024-04-01', 'EMP-001'],
            ['EMP-006', 'Maya Larasati', 'Software Engineer', 'teknologi', 'Jakarta', 'PREBOARDING', '2026-11-02', 'EMP-001'],
            ['EMP-007', 'Dimas Saputra', 'Shift Lead', 'operasional', 'Surabaya', 'ACTIVE', '2022-11-14', 'EMP-003'],
            ['EMP-008', 'Sinta Maharani', 'Spesialis Pemasaran', 'pemasaran', 'Bandung', 'ACTIVE', '2023-06-05', 'EMP-001'],
            ['EMP-009', 'Raka Adinata', 'Staf Operasional', 'operasional', 'Surabaya', 'OFFBOARDING', '2022-01-10', 'EMP-003'],
            ['EMP-010', 'Fajar Nugraha', 'HR Officer', 'sdm', 'Jakarta', 'ACTIVE', '2024-09-02', 'EMP-002'],
            ['EMP-011', 'Intan Kusuma', 'Staf Operasional', 'operasional', 'Bandung', 'ENDED', '2021-10-04', 'EMP-003'],
            ['EMP-012', 'Ayu Pertiwi', 'Finance Officer', 'keuangan', 'Surabaya', 'ACTIVE', '2025-02-03', 'EMP-001'],
        ];

        return array_map(function (array $row): array {
            [$id, $name, $job, $department, $branch, $status, $joined, $manager] = $row;

            return [
                'id' => $id, 'name' => $name, 'job' => $job, 'department' => $department,
                'department_name' => self::departments()[$department]['name'],
                'branch' => $branch, 'status' => $status, 'status_label' => self::statuses()[$status],
                'joined' => $joined, 'joined_label' => self::date($joined), 'manager' => $manager,
                'initials' => implode('', array_map(fn ($part) => mb_substr($part, 0, 1), explode(' ', $name))),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
                'end' => match ($status) {
                    'ENDED' => '2026-09-30', 'OFFBOARDING' => '2026-10-31', default => null
                },
            ];
        }, $rows);
    }

    public static function date(string $date): string
    {
        return CarbonImmutable::parse($date)->locale('id')->translatedFormat('j F Y');
    }

    public static function find(string $id): ?array
    {
        return collect(self::employees())->firstWhere('id', $id);
    }
}
