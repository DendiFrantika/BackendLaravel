/** Label status pendaftaran untuk tampilan Indonesia */
export function statusLabel(status) {
    const map = {
        pending: 'Menunggu verifikasi',
        confirmed: 'Dikonfirmasi',
        checked_in: 'Check-in',
        completed: 'Selesai',
        cancelled: 'Dibatalkan',
    };
    return map[status] || status || '—';
}

export function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('id-ID', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
}
