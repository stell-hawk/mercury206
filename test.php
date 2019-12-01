<?php
DEFINE('MDEBUG',TRUE);
include_once('Mercury206_library.php');
//Номер счетчика написан на самом счетчике 8 цифр под штрихкодом до пробела (цифры после пробела не нужны)
$merc=new mercury206_lib("192.168.XXX.XX","PORT","NUMBER");

echo "Serial number: ".$merc->get_serial()."\n";
echo "Battery level: ".$merc->get_battery()."\n";
$uip=$merc->get_uip();
echo "U: ".$uip['U']." I: ".$uip['I']." P: ".$uip['P']."\n";
$values=$merc->get_values();
echo "Power values: all: ".$values[0]." tarif1: ".$values[1]." tarif2: ".$values[2]." tarif3: ".$values[3]." tarif4: ".$values[4]."\n";


?>