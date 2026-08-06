<footer class="main-footer">
    <a href='https://www.pcnukabmagelang.or.id'/> NUMART PCNU <a href='https://www.pcnukabmagelang.or.id/'/> - KAB MAGELANG <a href='https://www.pcnukabmagelang.or.id/'/><br/>
 &#169; <strong>2020-<?= date("Y"); ?><a href='http://www.pcnukabmagelang.or.id'/>. BUMNU - <a href='https://www.pcnukabmagelang.or.id/'/>www.pcnukabmagelang.or.id

    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 1.1
    </div>
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<!-- Select2 -->
<script src="plugins/select2/js/select2.full.min.js"></script>
<!-- ChartJS -->
<script src="plugins/chart.js/Chart.min.js"></script>
<!-- Sparkline -->
<script src="plugins/sparklines/sparkline.js"></script>
<!-- JQVMap -->
<script src="plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="plugins/jqvmap/maps/jquery.vmap.usa.js"></script>
<!-- jQuery Knob Chart -->
<script src="plugins/jquery-knob/jquery.knob.min.js"></script>
<!-- daterangepicker -->
<script src="plugins/moment/moment.min.js"></script>
<script src="plugins/daterangepicker/daterangepicker.js"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- Summernote -->
<script src="plugins/summernote/summernote-bs4.min.js"></script>
<!-- overlayScrollbars -->
<script src="plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.js"></script>
<?php if (!empty($posAutoHideSidebar)) : ?>
<script>
  (function($) {
    function posSidebarPush() {
      return $('[data-widget="pushmenu"]').first();
    }

    function posSidebarCollapse() {
      if ($('body').hasClass('sidebar-collapse')) {
        return;
      }
      var $push = posSidebarPush();
      if ($push.length) {
        $push.PushMenu('collapse');
      } else {
        $('body').addClass('sidebar-collapse').removeClass('sidebar-open');
      }
    }

    function posSidebarToggle() {
      var $push = posSidebarPush();
      if ($push.length) {
        $push.PushMenu('toggle');
      } else {
        $('body').toggleClass('sidebar-collapse');
      }
    }

    $(function() {
      posSidebarCollapse();
      $(window).on('load', function() {
        setTimeout(posSidebarCollapse, 0);
      });
      $(document).on('keydown', function(e) {
        if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.keyCode === 83 || e.key === 's' || e.key === 'S')) {
          e.preventDefault();
          posSidebarToggle();
        }
      });
    });
  })(jQuery);
</script>
<?php endif; ?>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="dist/js/pages/dashboard.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="dist/js/demo.js"></script>

<script>
  $(function () {

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })
  });

  $(function () {
    $("#example1").DataTable();
  });
</script>

<!-- Export scripts moved out of global footer to avoid breaking pages that don't need them -->
<!-- If needed, include these files only on report pages: dist/js/xlsx.core.min.js, dist/js/FileSaver.js, dist/js/tableexport.min.js -->

<!-- Convert HTML to Img -->
<script src="dist/js/html2canvas.min.js"></script>
</body>
</html>