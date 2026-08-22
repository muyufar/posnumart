<?php 
  include __DIR__ . '/_header-artibut.php';
?>
<?php  
  $levelLogin = $_SESSION['user_level'];
  $status = $_SESSION['user_status'];
    if ( $status === '0') {
    echo"
          <script>
            alert('Akun Tidak Aktif');
            window.location='./';
          </script>";
  }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <base href="<?= htmlspecialchars(numart_web_base(), ENT_QUOTES, 'UTF-8'); ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>NUMART</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Font Awesome -->
  <!-- Favicon -->
  <link rel="icon" type="img/png" sizes="32x32" href="https://pos.numartmagelang.com/dist/img/logobumnupacnu.png">
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="dist/css/font-awesome.min.css">
  <!-- Ionicons -->
  <!-- <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css"> -->
  <!-- Tempusdominus Bbootstrap 4 -->
  <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.css">
  <!-- iCheck -->
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- JQVMap -->
  <link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="plugins/daterangepicker/daterangepicker.css">
  <!-- summernote -->
  <link rel="stylesheet" href="plugins/summernote/summernote-bs4.css">
  <!-- Google Font: Source Sans Pro -->
  <!-- <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet"> -->
  <!-- Select2 -->
  <link rel="stylesheet" href="plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

  <link rel="stylesheet" type="text/css" href="dist/css/style.css">
  <link rel="stylesheet" type="text/css" href="dist/css/numart-dark-mode.css">
  <script>
    (function(){try{var v=localStorage.getItem('numart_dark_mode');if(v===null)v=localStorage.getItem('numart_pos_dark_mode');if(v==='1')document.documentElement.classList.add('numart-dark-mode');}catch(e){}})();
  </script>


  <!-- jQuery -->
  <script src="plugins/jquery/jquery.min.js"></script>
  <!-- jQuery UI 1.11.4 -->
  <script src="plugins/jquery-ui/jquery-ui.min.js"></script>
  <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
  <script>
    $.widget.bridge('uibutton', $.ui.button)
  </script>
  <!-- Bootstrap 4 -->
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script type="text/javascript">
    window.history.forward();
    function noBack() { window.history.forward(); }
  </script>
</head>
<body onload="noBack();"onpageshow="if (event.persisted) noBack();" onunload="" class="hold-transition sidebar-mini layout-fixed"  >
<script src="dist/js/numart-dark-mode.js"></script>
<script>if(window.NumartDarkMode)NumartDarkMode.applyEarly();</script>
<div class="wrapper">
