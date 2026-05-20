<?php  
  include 'aksi/koneksi.php';
  require 'vendor/export-import/autoload.php';


  if(isset($_POST['submit'])){
    $err    = "";
    $ekstensi   = "";
    $success  = "";

    $file_name = $_FILES['filexls']['name']; // untuk mendapatkan nama file yg diupload
    $file_data = $_FILES['filexls']['tmp_name']; // untuk mendapatkan temporary data

    if(empty($file_name)) {
      $err .= "<li>Silahkan Masukan File yang Kamu Inginkan</li>";
    } else {
      $ekstensi = pathinfo($file_name)['extension'];
    }

    $ekstensi_allowed = array("xls","xlsx");
    
    // Cek Jika Data Tidak Sesuai
    if(!in_array($ekstensi, $ekstensi_allowed)){
      $err .= "<li>Silahkan masukan file tipe xls, atau xlsx. File yang kamu masukkan <b>$file_name</b> punya tipe <b>$ekstensi</b></li>";
    }

    // Proses Ambil data dari file xlx atau xlsx
    if(empty($err)) {
      $reader     = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_data);
      $spreadsheet  = $reader->load($file_data);
      $sheetData    = $spreadsheet->getActiveSheet()->toArray();

      $jumlahData = 0;
      for ( $i = 1; $i < count($sheetData); $i++ ){
        $soh_stock_opname_id        = $_POST['soh_stock_opname_id'];
        $soh_user                   = $_POST['soh_user'];
        $soh_barang_cabang          = $_POST['soh_barang_cabang'];
        $soh_barang_kode            = $sheetData[$i]['1'];
        $soh_stock_fisik            = $sheetData[$i]['2'];
        $soh_note                   = $sheetData[$i]['3'];
        
        $soh_barang_kode_slug           = str_replace(" ", "-", $soh_barang_kode);

        $barang         = mysqli_query($conn, "SELECT barang_id, barang_nama, barang_stock FROM barang WHERE barang_cabang = $soh_barang_cabang && barang_status = 1 && barang_kode_slug = '".$soh_barang_kode_slug."' ");
        $barang         = mysqli_fetch_array($barang);
        $barang_id      = $barang['barang_id'];
        $barang_stock   = $barang['barang_stock'];
        $barang_nama    = $barang['barang_nama'];

        if ( $barang_id == null ) {
            echo '
                <script>
                  alert("Kode Barang/Barcode '.$soh_barang_kode.' TIDAK ADA di DATA Barang !! Silahkan Sesuaikan & Cek Kembali dari penulisan Huruf Besar, Kecil, Spasi beserta KODE HARUS SESUAI !!");
                  document.location.href = "";
                </script>
            '; exit();
        } 
        
        $soh_selisih                = $soh_stock_fisik - $barang_stock;
        $soh_tipe                   = 1;
        $soh_date                   = date("Y-m-d");
        $soh_datetime               = date("d F Y g:i:s a");
        

            $kodeEsc = mysqli_real_escape_string($conn, (string) $soh_barang_kode);
            $noteEsc = mysqli_real_escape_string($conn, (string) $soh_note);
            $dtEsc = mysqli_real_escape_string($conn, $soh_datetime);
            $sql1 = "INSERT INTO stock_opname_hasil (
                soh_stock_opname_id, soh_barang_id, soh_barang_kode, soh_barang_stock_system, soh_stock_fisik,
                soh_selisih, soh_note, soh_date, soh_datetime, soh_tipe, soh_user, soh_barang_cabang
            ) VALUES (
                " . (int) $soh_stock_opname_id . ",
                " . (int) $barang_id . ",
                '$kodeEsc',
                " . (int) $barang_stock . ",
                " . (int) $soh_stock_fisik . ",
                " . (int) $soh_selisih . ",
                '$noteEsc',
                '" . mysqli_real_escape_string($conn, $soh_date) . "',
                '$dtEsc',
                " . (int) $soh_tipe . ",
                " . (int) $soh_user . ",
                " . (int) $soh_barang_cabang . "
            )";
            mysqli_query($conn,$sql1);

            $jumlahData++;
          
      } 

      $query = "UPDATE stock_opname SET 
            stock_opname_status           = '1',
            stock_opname_user_upload      = '$soh_user',
            stock_opname_date_upload      = '$soh_date',
            stock_opname_datetime_upload  = '$soh_datetime'
            WHERE stock_opname_id         = $soh_stock_opname_id && stock_opname_cabang = $soh_barang_cabang;
            ";
      mysqli_query($conn, $query);

      // Jika Data Sukses Insert
      if($jumlahData > 0){
        if ( $soh_barang_cabang < 1 ) {
             $dataPilihCabangTeksUtama = "Pusat";
        } else {
            $dataPilihCabangTeksUtama = "Cabang ".$cabangId." ";
        }
        $success = "$jumlahData Data Berhasil Insert ke Tabel Stock Opname Gudang ".$dataPilihCabangTeksUtama." ";
      }
    }

    // Jika Error
    if($err) {
      ?>
      <!-- <div class="alert alert-danger">
        <ul><?php echo $err ?></ul>
      </div> -->
      <?php  
        echo '
          <script>
            alert("'.$err.'");
          </script>
        ';
      ?>
      <?php
    }

    // Jika Sukses
    if($success){
      ?>
      <!-- <div class="alert alert-danger">
        <ul><?php echo $success ?></ul>
      </div>-->
      <?php   
        echo '
          <script>
            alert("'.$success.'");
            document.location.href = "stock-opname-keseluruhan-import-detail?id='.base64_encode($soh_stock_opname_id).'&tipe='.base64_encode($soh_tipe).'";
          </script>
        ';
      ?>
      <?php
    }


  }
?>