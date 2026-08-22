<?php 
  	include '_header-artibut.php';

  	$keyword 	= $_GET["keyword"];

	$query = "SELECT * FROM invoice WHERE penjualan_invoice LIKE '%$keyword%' 
											 ORDER BY invoice_id DESC";
	$data = query($query);
?>

				<?php  
					if (count($data) == 0 ) {
						echo '
							<center>
								<p>No. Invoice <b><i>'. $keyword . '</i></b> Tidak Ada !!</p>
							</center>
						';
					}
				?>

                <div class="row">

                  <?php foreach( $data as $rowProduct) : ?>
                  <?php if ( $rowProduct['invoice_cabang'] == $sessionCabang && $rowProduct['invoice_kurir'] == $userId ) { ?>
                  <?php if ( $rowProduct['invoice_status_kurir'] > 0 ) { ?>
                  <?php  
                        $id = base64_encode($rowProduct['invoice_id']);

                        // Mencari Data Customer
                        $customer = $rowProduct['invoice_customer'];
                        $DataCustomer = mysqli_query( $conn, "select customer_nama, customer_alamat, customer_tlpn from customer where customer_id = '".$customer."'");
              					$dc = mysqli_fetch_array($DataCustomer); 

              					$dataNama    = $dc["customer_nama"];
              					$dataAlamat  = $dc["customer_alamat"];
              					$dataNoWa    = $dc["customer_tlpn"];

                        $alamat = str_replace(" ", "+", $dataAlamat);
                        $no_wa  = substr_replace($dataNoWa,'62',0,1);
                  ?>
                  <div class="col-md-4 col-lg-4">
                      <div class="card-desktop-box">
                          <div class="cdb-top">
                              <div class="row">
                                  <div class="col-6">
                                      <div class="cdb-top-date">
                                        <i class="fa fa-clock-o" aria-hidden="true"></i> 
                                        <span><?= $rowProduct['invoice_tgl']; ?></span>
                                      </div>
                                  </div>
                                  <div class="col-6">
                                      <div class="cdb-top-info">
                                          <div class="cti cdb-top-info-status">
                                              <?php 
                                                $statusKurir = $rowProduct['invoice_status_kurir'];
                                                if ( $statusKurir == 1 ) {
                                                  $sk = "<span class='badge badge-warning'>Packing</span>";
                                                } elseif ( $statusKurir == 2 ) {
                                                  $sk = "<span class='badge badge-success'>Proses</span>";
                                                } elseif ( $statusKurir == 3 ) {
                                                  $sk = "<span class='badge badge-primary'>Selesai</span>";
                                                } elseif ( $statusKurir == 4 ) {
                                                  $sk = "<span class='badge badge-danger'>Gagal</span>";
                                                } else {
                                                  $sk = "Tanpa Kurir";
                                                }
                                                echo $sk;
                                              ?>      
                                          </div>
                                          <div class="cti cdb-top-info-action">
                                              <div class="btn-group">
                                                <a class="btn dropdown-toggle" data-toggle="dropdown" href="#"> <i class="fa fa-ellipsis-v"></i></a>
                                                <ul class="dropdown-menu">
                                                  <li><a href="penjualan-zoom?no=<?= $id; ?>" target="_blank">Detail Invoice</a></li>

                                                  <li><a href="https://api.whatsapp.com/send?phone=<?= $no_wa; ?>&text=Hallo <?= $dataNama; ?> Kami dari *<?= $dataTokoLogin['toko_nama']; ?> <?= $dataTokoLogin['toko_kota']; ?>* akan mengirimkan Produk dengan No. Invoice <?= $rowProduct['penjualan_invoice']; ?>" target="_blank">WhatsApp</a></li>

                                                  <li><a href="nota-cetak?no=<?= $rowProduct['invoice_id']; ?>-no-invoice-<?= $rowProduct['penjualan_invoice']; ?>" target="_blank">Print</a></li>

                                                  <li><a href="https://www.google.com/maps/search/<?= $alamat; ?>" target="_blank">GPS</a></li>
                                                </ul>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          <div class="cdb-detail">
                              <div class="cdb-detail-title">
                                No. Invoice: <?= $rowProduct['penjualan_invoice']; ?>
                              </div>
                              <div class="cdb-detail-desc">
                                Terkirim: <?= $rowProduct['invoice_date_selesai_kurir']; ?>
                              </div>
                          </div>
                          <div class="cdb-bottom">
                              <div class="row">
                                  <div class="col-6">
                                    <div class="cdb-bottom-left">
                                        <div class="cbl-title">
                                            Sub Total:
                                        </div>
                                        <div class="cbl-desc">
                                            Rp <?= number_format($rowProduct['invoice_sub_total'], 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                  </div>
                                  <div class="col-6">
                                      <div class="cdb-bottom-right">
                                         <a href="kurir-data-edit?id=<?= $id; ?>" class="btn btn-primary">
                                            Edit Status
                                         </a>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>

                  <?php } ?>
                  <?php } ?>
                  <?php endforeach; ?>
                </div>
