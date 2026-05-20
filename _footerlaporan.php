<script>
  function setDefaultDates() {
    const awal = '<?= $_POST['tanggal_awal'] ?? null ?>'
    const akhir = '<?= $_POST['tanggal_akhir'] ?? null ?>'
    const formatDate = (date) => date.toISOString().split('T')[0]; // Format ke YYYY-MM-DD
    if (awal && akhir) {
      const tgl_akhir = new Date(akhir);
      const tgl_awal = new Date(awal)
      document.getElementById('tanggal_akhir').value = formatDate(tgl_akhir);
      document.getElementById('tanggal_awal').value = formatDate(tgl_awal);
    } else {
      const today = new Date();
      // Set tanggal_akhir ke hari ini
      document.getElementById('tanggal_akhir').value = formatDate(today);

      // Set tanggal_awal ke 1 bulan sebelum hari ini
      const lastMonth = new Date(today.setMonth(today.getMonth() - 1));
      document.getElementById('tanggal_awal').value = formatDate(lastMonth);
    }
  }

  window.onload = setDefaultDates; // Panggil fungsi saat halaman dimuat
</script>