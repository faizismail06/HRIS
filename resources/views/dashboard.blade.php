<x-app-layout title="Ringkasan HR" active="dashboard" :dashboard="true">
            <section id="ringkasan" class="overview" aria-labelledby="page-title">
                <div class="page-heading"><div><h1 id="page-title">Selamat pagi, tim HR.</h1><p>Gambaran tim dan pekerjaan Anda hari ini, dalam satu tempat.</p></div><div class="snapshot"><x-icon name="calendar" size="17"/><span>{{ $date }}<small>Snapshot contoh · {{ $snapshot }}</small></span></div></div>


                <nav class="section-links" aria-label="Bagian ringkasan"><a href="#antrean">Tugas {{ count($tasks) }}</a><a href="#agenda">Agenda hari ini</a></nav>
                <div class="overview-grid">
                <section class="attendance-strip" aria-labelledby="attendance-title">
                    <div class="section-heading"><div><h2 id="attendance-title">Kehadiran hari ini</h2><p>Semua cabang · {{ array_sum(array_column($attendance, 'count')) }} karyawan terjadwal</p></div><span class="data-label">Data sintetis</span></div>
                    <div class="attendance-bar" role="img" aria-label="Kehadiran contoh: 118 hadir valid, 9 belum mulai, 5 cuti disetujui, 7 belum lengkap, 3 tidak hadir.">
                        @foreach($attendance as $status)
                            <span class="bar-segment tone-{{ $status['tone'] }}" style="flex-grow: {{ $status['count'] }}"></span>
                        @endforeach
                    </div>
                    <dl class="attendance-legend">
                        @foreach($attendance as $status)
                            <div><dt><span class="status-dot tone-{{ $status['tone'] }}"></span>{{ $status['label'] }}</dt><dd>{{ $status['count'] }} <span>orang</span></dd></div>
                        @endforeach
                    </dl>
                    <p class="attendance-note"><x-icon name="info" size="14"/>Hadir valid termasuk 8 karyawan terlambat. Belum lengkap berbeda dari tidak hadir.</p>
                </section>
                    <section id="agenda" class="agenda-panel" aria-labelledby="agenda-title"><div class="section-heading"><div><h2 id="agenda-title">Agenda hari ini</h2><p>Selasa, 20 Oktober · WIB</p></div><x-icon name="calendar" size="19"/></div><ol class="agenda-list">
                        @foreach($agenda as $event)
                            <li><time datetime="2026-10-20T{{ str_replace('.', ':', $event['time']) }}:00+07:00">{{ $event['time'] }}</time><div><span class="agenda-type">{{ $event['type'] }}</span><h3>{{ $event['title'] }}</h3><p>{{ $event['detail'] }}</p></div></li>
                        @endforeach
                    </ol><p class="agenda-note">Jadwal contoh, bukan undangan kalender.</p></section>
                </div>
            </section>

            <div class="work-grid">
                <section id="antrean" class="task-panel" aria-labelledby="tasks-title">
                    <div class="section-heading task-heading"><div><h2 id="tasks-title">Perlu ditindaklanjuti <span class="heading-count">{{ count($tasks) }}</span></h2><p>Enam pekerjaan lintas tim, diurutkan dari tenggat terdekat.</p></div><span class="status-badge badge-urgent"><span class="status-dot"></span>2 perlu segera</span></div>
                    <noscript><p class="noscript-notice">JavaScript nonaktif. Semua tugas tetap tersedia; buka tiap baris untuk membaca detail. Aktifkan JavaScript untuk memakai filter.</p></noscript>
                    <div class="task-controls" data-filter-controls hidden>
                        <div class="category-filters" role="group" aria-label="Kategori tugas">
                            <button type="button" class="filter-button" data-category="all" aria-pressed="true">Semua <span>6</span></button>
                            <button type="button" class="filter-button" data-category="Absensi" aria-pressed="false">Absensi <span>3</span></button>
                            <button type="button" class="filter-button" data-category="Administrasi" aria-pressed="false">Administrasi <span>3</span></button>
                        </div>
                        <div class="filter-fields">
                            <label class="search-field"><span class="sr-only">Cari tugas, nomor, atau karyawan</span><x-icon name="search" size="18"/><input type="search" data-task-search placeholder="Cari tugas atau karyawan…" autocomplete="off"></label>
                            <label class="branch-filter"><span class="sr-only">Cabang pada antrean tugas</span><select data-branch-filter><option value="all">Semua cabang</option><option>Jakarta</option><option>Bandung</option><option>Surabaya</option></select></label>
                        </div>
                        <div class="filter-meta"><p role="status" aria-live="polite" aria-atomic="true" data-result-count>Menampilkan 6 dari 6 tugas</p><label class="urgent-filter"><input type="checkbox" data-urgent-filter> Perlu segera saja</label></div>
                    </div>
                    <div class="task-list" aria-label="Daftar tugas sintetis">
                        <div class="task-columns" aria-hidden="true"><span>Tugas / Karyawan</span><span>Tenggat</span><span></span></div>
                        @foreach($tasks as $task)
                            <details class="task-row" data-task data-category="{{ $task['category'] }}" data-branch="{{ $task['branch'] }}" data-priority="{{ $task['priority'] }}" data-search="{{ $task['id'].' '.$task['title'].' '.$task['name'].' '.$task['department'].' '.$task['branch'] }}">
                                <summary><span class="task-person"><span class="avatar avatar-{{ strtolower($task['initials']) }}" aria-hidden="true">{{ $task['initials'] }}</span><span class="task-copy"><strong>{{ $task['title'] }}</strong><span>{{ $task['name'] }}<span class="copy-separator">·</span>{{ $task['branch'] }}</span></span></span><span class="task-due {{ $task['priority'] === 'urgent' ? 'due-urgent' : '' }}">@if($task['priority'] === 'urgent')<x-icon name="clock" size="14"/>@endif{{ $task['due'] }}</span><span class="row-action"><span>Detail</span><x-icon name="chevron" size="15"/></span></summary>
                                <div class="task-detail"><div class="detail-reference"><span>{{ $task['id'] }}</span><span>{{ $task['category'] }}</span><span>{{ $task['department'] }}</span></div><p>{{ $task['description'] }}</p><dl><div><dt>Pemilik tindak lanjut</dt><dd>{{ $task['owner'] }}</dd></div><div><dt>Langkah berikutnya</dt><dd>{{ $task['next'] }}</dd></div></dl><p class="readonly-note"><x-icon name="info" size="15"/>Pratinjau baca saja. Persetujuan dan dokumen belum tersedia.</p></div>
                            </details>
                        @endforeach
                    </div>
                    <div class="empty-state" data-empty-state hidden><x-icon name="search" size="28"/><h3>Tidak ada tugas yang cocok</h3><p>Coba nama lain atau perluas kategori dan cabang.</p><button type="button" class="secondary-button" data-reset-filters>Hapus semua filter</button></div>
                    <div class="task-footer"><x-icon name="check" size="15"/><span>Tugas diurutkan berdasarkan tenggat, yang terdekat lebih dulu.</span></div>
                </section>


            </div>

</x-app-layout>
