<?php
//https://www.incotexcom.ru/files/em/docs/mercury-protocol-obmena-1.pdf
//Известный баг с получением 4 тарифа не всегда прилетают все данные.

class mercury206_lib
{
    public $socket;
    public $host;
    public $port;
    public $addr;
    public $connected=false;
    public $timeout=1;
    
function mercury206_lib($host,$port,$addr)
{
$this->host=$host;
$this->port=$port;
$this->addr=str_pad(dechex($addr),8,"0",STR_PAD_LEFT);
$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$this->socket)echo 'Unable to create socket';
socket_set_option($this->socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>$this->timeout, "usec"=>0));
//echo 'created socket'.$this->socket.'\n';
$this->connected=@socket_connect($this->socket,$this->host,$this->port);
if (!$this->connected)echo 'Unable to connect to '.$this->host.":".$this->port;

}
function send($in)
{
		$in=hex2bin($in);
    socket_send($this->socket,$in,strlen($in),MSG_EOF);
    $ret=socket_read($this->socket,1024);
    //шлем дополнительную команду чтобы счетчик не разорвал соединение (актуально только для чтения кВт*ч - там слишком большой ответ) в остальных случаях можно и не слать
    socket_send($this->socket,hex2bin("f2"),strlen($in),MSG_EOF);
    $ret.=socket_read($this->socket,1024);
    return bin2hex($ret);
}

function send_ret($code)
{
	$msg=$this->addr.$code;
	$msg=$msg.$this->crc16_modbus($msg);
	if(MDEBUG)echo "\nsend: ".$this->nice_hex($msg)."\n";
	$ret=$this->send($msg);
	if(MDEBUG)echo "\nret(".strlen($ret).") :".$this->nice_hex($ret)."\n";
	return $ret=$this->decoderet($ret);
}

//Получить серийный номер
function get_serial()
{
	$ret=$this->send_ret("2F");
	if(!$ret)return false;
	return hexdec(implode("",$ret));
}

//Заряд батареи
function get_battery()
{
	$ret=$this->send_ret("29");
	if(!$ret)return false;
	return implode(".",$ret);
}

//мгновенные значения (U,I,P)
function get_uip()
{
	$ret=$this->send_ret("63");
	if(!$ret)return false;
	$U=$ret[5].$ret[6];
	$I=$ret[7].$ret[8];
	$P=$ret[9].$ret[10].$ret[11];
	//$out['U']=$U[0].$U[1].$U[2].".".$U[3];
	$out['U']=(($U[0]*1000.0) + ($U[1]*100.0) + ($U[2]*10.0) + ($U[3]*1.0)) / 10.0;
	//$out['mI']=$I[0].$I[1].".".$I[2].$I[3];
	$out['mA']=(($I[0]*1000.0) + ($I[1]*100.0) + ($I[2]*10.0) + ($I[3]*1.0)) * 10.0;	//вывод значения тока в mA/ч
	$out['I']=( (($I[0]*1000.0) + ($I[1]*100.0) + ($I[2]*10.0) + ($I[3]*1.0)) * 10.0 ) / 1000.0;	//вывод значения тока в A/ч
	//$out['P']=$P[0].$P[1].$P[2].".".$P[3].$P[4].$P[5];
	$out['W']=( ($P[0]*100000.0) + ($P[1]*10000.0) + ($P[2]*1000.0) + ($P[3]*100.0) + ($P[4]*10.0) + ($P[5]*1.0) );	//вывод значения мощности в Вт/ч
	$out['P']=( ($P[0]*100000.0) + ($P[1]*10000.0) + ($P[2]*1000.0) + ($P[3]*100.0) + ($P[4]*10.0) + ($P[5]*1.0) ) / 1000.0;	//вывод значения мощности в кВт/ч
	return $out;
}

//значения энергии
function get_values()
{
	$ret=$this->send_ret("27");
	if(!$ret)return false;
	$out[1]=$ret[5].$ret[6].$ret[7].".".$ret[8];
	$out[2]=$ret[9].$ret[10].$ret[11].".".$ret[12];
	$out[3]=$ret[13].$ret[14].$ret[15].".".$ret[16];
	$out[4]=$ret[17].$ret[18].$ret[19].".".$ret[20];
	//суммированные значения
	$out[0]=$out['1']+$out['2']+$out['3']+$out['4'];
	return $out;
}



//Удаляем лишнее из ответа
function decoderet($msg)
{
	$len=strlen($msg);
	$msg=str_split($msg,2);
	$len=count($msg);
	if($len<6){echo "string too small($len <6)\n";return false;}
	if($msg[0].$msg[1].$msg[2].$msg[3]!=$this->addr){echo "line is busy\n";return false;}
	
	unset($msg[$len-1],$msg[$len-2],$msg[1],$msg[2],$msg[3],$msg[0],$msg[4]);
	return $msg;	
}

// Format input string as nice hex
function nice_hex($str) {
    return strtoupper(implode(' ',str_split($str,2)));
}


function crc16_modbus($msg)
{
    $data = pack('H*',$msg);
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++)
    {
        $crc ^=ord($data[$i]);
        for ($j = 8; $j !=0; $j--)
        {
            if (($crc & 0x0001) !=0)
            {
                $crc >>= 1;
                $crc ^= 0xA001;
            }
            else $crc >>= 1;
        }
    }
    $crc=sprintf('%04X', $crc);
    return $crc[2].$crc[3].$crc[0].$crc[1];
}



}
?>
