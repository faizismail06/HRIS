<nav class="section-links attendance-navigation" aria-label="Keuangan">
    <a href="{{ route('prototype.payroll', $page === 'payroll' ? $filters : []) }}" @if($page === 'payroll') aria-current="page" @endif>Batch payroll</a>
    <a href="{{ route('prototype.reimbursements', $page === 'reimbursements' ? $filters : []) }}" @if($page === 'reimbursements') aria-current="page" @endif>Reimbursement</a>
</nav>
