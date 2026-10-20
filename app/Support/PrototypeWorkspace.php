<?php

namespace App\Support;

/** Curated, synthetic workspace messages and search projections, never an authorized production index. */
class PrototypeWorkspace
{
    public static function readStates(): array
    {
        return ['UNREAD' => 'Belum dibaca', 'READ' => 'Sudah dibaca', 'ARCHIVED' => 'Diarsipkan'];
    }

    public static function deliveries(): array
    {
        return ['SENT' => 'Terkirim dalam skenario', 'FAILED' => 'Gagal · menunggu retry', 'QUEUED' => 'Antre · jam tenang', 'NONE' => 'Dalam aplikasi saja'];
    }

    public static function notifications(): array
    {
        return [
            ['id' => 'NTF-006', 'at' => '2026-10-20 08:40', 'title' => 'Pengajuan menunggu tahap HR', 'body' => 'CUT-041 milik Nadia Pramesti menunggu peninjauan. Buka catatan sumber untuk melihat status terkini.', 'state' => 'UNREAD', 'delivery' => 'NONE', 'key' => 'CUT-041:HR:ACTIVE', 'attempts' => 0, 'retry' => null, 'route' => 'prototype.requests.show', 'parameters' => ['requestId' => 'CUT-041']],
            ['id' => 'NTF-005', 'at' => '2026-10-19 20:30', 'title' => 'Pengiriman pemberitahuan koreksi gagal', 'body' => 'COR-026 tetap dalam tinjauan. Kegagalan email tidak membatalkan atau mengulang keputusan pada catatan sumber.', 'state' => 'UNREAD', 'delivery' => 'FAILED', 'key' => 'COR-026:HR:ACTIVE', 'attempts' => 3, 'retry' => '2026-10-20 09:00 WIB', 'route' => 'prototype.requests.show', 'parameters' => ['requestId' => 'COR-026']],
            ['id' => 'NTF-004', 'at' => '2026-10-20 08:15', 'title' => 'Kontrak mendekati akhir periode', 'body' => 'CTR-001 perlu tindak lanjut. Pengingat tidak memperpanjang kontrak atau mengubah tanggal akhir kerja.', 'state' => 'UNREAD', 'delivery' => 'QUEUED', 'key' => 'CTR-001:REMINDER:2026-10-20', 'attempts' => 0, 'retry' => '2026-10-20 09:00 WIB', 'route' => 'prototype.contracts.show', 'parameters' => ['record' => 'CTR-001']],
            ['id' => 'NTF-003', 'at' => '2026-10-20 08:00', 'title' => 'Batch impor memerlukan perbaikan', 'body' => 'IMP-001 memiliki masalah validasi. Buka batch untuk melihat baris, kolom dan langkah perbaikan.', 'state' => 'READ', 'delivery' => 'NONE', 'key' => 'IMP-001:INVALID', 'attempts' => 0, 'retry' => null, 'route' => 'prototype.imports.show', 'parameters' => ['batch' => 'IMP-001']],
            ['id' => 'NTF-002', 'at' => '2026-10-15 15:00', 'title' => 'Status penyelesaian klaim diperbarui', 'body' => 'Buka EXP-002 untuk memeriksa catatan penyelesaian. Pesan email contoh hanya membawa referensi dan tautan, tanpa nominal atau bukti pengeluaran.', 'state' => 'READ', 'delivery' => 'SENT', 'key' => 'EXP-002:SET-EXP-002A', 'attempts' => 1, 'retry' => null, 'route' => 'prototype.reimbursements.show', 'parameters' => ['claim' => 'EXP-002']],
            ['id' => 'NTF-001', 'at' => '2026-10-02 09:00', 'title' => 'Clearance masih membutuhkan tindak lanjut', 'body' => 'LIF-003 masih memiliki kewajiban aset terbuka. Akses aplikasi dalam skenario telah dicabut; arsip pesan tidak menutup tugas.', 'state' => 'ARCHIVED', 'delivery' => 'SENT', 'key' => 'LIF-003:CLEARANCE:OPEN', 'attempts' => 1, 'retry' => null, 'route' => 'prototype.lifecycle.show', 'parameters' => ['record' => 'LIF-003']],
        ];
    }

    public static function unread(): int
    {
        return count(array_filter(self::notifications(), fn ($item) => $item['state'] === 'UNREAD'));
    }

    public static function notification(string $id): ?array
    {
        return collect(self::notifications())->firstWhere('id', $id);
    }

    public static function categories(): array
    {
        return ['employees' => 'Karyawan', 'requests' => 'Pengajuan', 'employment' => 'Kontrak, surat & karier', 'lifecycle' => 'Siklus kerja', 'assets' => 'Aset', 'attendance' => 'Shift kehadiran', 'payroll' => 'Batch payroll', 'claims' => 'Klaim', 'imports' => 'Batch impor', 'periods' => 'Periode kehadiran'];
    }

    private static function entry(string $category, string $id, string $title, string $context, string $route, array $parameters, string $keywords = ''): array
    {
        return compact('category', 'id', 'title', 'context', 'route', 'parameters', 'keywords');
    }

    public static function entries(): array
    {
        $entries = [];
        foreach (PrototypePeople::employees() as $person) {
            $entries[] = self::entry('employees', $person['id'], $person['name'], $person['job'].' · '.$person['branch'].' · '.$person['status_label'], 'prototype.employees.show', ['employee' => $person['id']], $person['department_name']);
        }
        foreach (PrototypeRequests::all() as $item) {
            $entries[] = self::entry('requests', $item['id'], $item['type_label'].' · '.$item['person']['name'], $item['status_label'], 'prototype.requests.show', ['requestId' => $item['id']]);
        }
        foreach (array_keys(PrototypeEmployment::definitions()) as $kind) {
            foreach (PrototypeEmployment::records($kind) as $item) {
                $entries[] = self::entry('employment', $item['id'], $item['title'].' · '.$item['person']['name'], $item['status_label'].' · '.$item['start'], 'prototype.'.$kind.'.show', ['record' => $item['id']], $item['number'] ?? '');
            }
        }
        foreach (PrototypeLifecycle::cases() as $item) {
            $entries[] = self::entry('lifecycle', $item['id'], $item['title'], $item['status_label'], 'prototype.lifecycle.show', ['record' => $item['id']]);
        }
        foreach (PrototypeLifecycle::assets() as $item) {
            $entries[] = self::entry('assets', $item['id'], $item['name'], $item['person']['name'].' · '.$item['status_label'], 'prototype.assets.show', ['record' => $item['id']], $item['serial'] ?? '');
        }
        foreach (PrototypeAttendance::records() as $item) {
            $entries[] = self::entry('attendance', $item['id'], $item['person']['name'].' · '.$item['date'], $item['shift'].' · '.$item['status_label'], 'prototype.attendance.show', ['record' => $item['id'], 'date' => $item['date'], 'employee' => $item['person']['id']]);
        }
        foreach (PrototypeFinance::payrolls() as $item) {
            $entries[] = self::entry('payroll', $item['id'], $item['label'], $item['status_label'].' · '.$item['version'], 'prototype.payroll.show', ['batch' => $item['id']]);
        }
        foreach (PrototypeFinance::claims() as $item) {
            $entries[] = self::entry('claims', $item['id'], $item['title'].' · '.$item['person']['name'], $item['status_label'], 'prototype.reimbursements.show', ['claim' => $item['id']]);
        }
        foreach (PrototypeAdministration::imports() as $item) {
            $entries[] = self::entry('imports', $item['id'], $item['file'], PrototypeAdministration::importStates()[$item['status']], 'prototype.imports.show', ['batch' => $item['id']]);
        }
        foreach (PrototypeReports::periods() as $item) {
            $entries[] = self::entry('periods', $item['id'], $item['title'], $item['status_label'], 'prototype.attendance-periods.show', ['period' => $item['id']]);
        }

        return $entries;
    }
}
