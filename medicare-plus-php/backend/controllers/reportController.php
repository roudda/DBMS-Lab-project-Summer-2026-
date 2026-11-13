<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';
function dashboardStats(): void {
 $pdo=getDb();try{
  $q=fn($sql)=>(int)$pdo->query($sql)->fetchColumn();
  $revenue=(float)$pdo->query('SELECT COALESCE(SUM(amount_paid),0) FROM Payment')->fetchColumn();
  $todayAppts=$q("SELECT COUNT(*) FROM Appointment WHERE appt_date=CURDATE() AND status='Scheduled'");
  $occupied=$q("SELECT COUNT(*) FROM Room WHERE status='Occupied'");
  $low=$q('SELECT COUNT(*) FROM Inventory WHERE quantity_in_stock<=reorder_level');
  $exp=$q('SELECT COUNT(*) FROM Inventory WHERE expiry_date IS NOT NULL AND expiry_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)');
  $stats=['patients'=>$q('SELECT COUNT(*) FROM Patient'),'doctors'=>$q('SELECT COUNT(*) FROM Doctor'),'appointments_today'=>$todayAppts,'occupied_rooms'=>$occupied,'low_stock'=>$low,'expiring_soon'=>$exp,'unpaid_bills'=>$q("SELECT COUNT(*) FROM Bill WHERE status!='Paid'"),'revenue'=>$revenue];
  jsonResponse(200,$stats);
 }catch(Exception $e){error_log($e->getMessage());jsonResponse(500,['message'=>'Could not generate dashboard statistics.']);}
}
