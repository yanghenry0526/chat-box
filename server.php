<?php
error_reporting(E_ALL);	//錯誤訊息回報
set_time_limit(0);	// 把時間射程無限，避免超時
date_default_timezone_set('Asia/Taipei');

$host = 'localhost'; //host
$port = '8080'; //port
$null = NULL; //null var
$address = "127.0.0.1"; //本機的address

//建立 TCP/IP socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//可以重複連線，兩人以上
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

//綁訂到指定address和port
socket_bind($socket, $host, $port);

//監聽窗口
socket_listen($socket);
//確認有建立socket和listen
echo "OK\nBinding the socket on $host:$port ... ";
echo "OK\nNow ready to accept connections.\nListening on the socket ... \n";  

//因為可能會有很多端socket ，用array存客戶端
$clients = array($socket);


while (true) {
	//方便管理多組連接
	$changed = $clients;
	//returns the socket resources in $changed array
	socket_select($changed, $null, $null, 0, 10);
	
	//檢查新的socket
	if (in_array($socket, $changed)) {
		$socket_new = socket_accept($socket); //接收新的socket
		$clients[] = $socket_new; //加入client的array
		
		$header = socket_read($socket_new, 1024); //讀取socket(header)中的資料
		perform_handshaking($header, $socket_new, $host, $port); //執行websocket handshake
		
		socket_getpeername($socket_new, $ip); //獲取連接的 ip address
		$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); //準備給json檔新client 的 data
		send_message($response); //通知連線中的clinet有新client加入
		
		//給新的socket空間
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]); //使用unset刪除變數指定物
	}
	
	//把所有socket跑過一次
	foreach ($changed as $changed_socket) {	
		
		//檢查傳入的data
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
		{
			$received_text = unmask($buf); //解封包
			$tst_msg = json_decode($received_text, true); //json decode 
			$user_name = $tst_msg['name']; //sender name
			$user_message = $tst_msg['message']; //message text
			$user_color = $tst_msg['color']; //color
			
			//發送data給client端
			$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));
			send_message($response_text); //send data
			break 2; //exist this loop
		}
		
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		// 檢查斷開連接的client
		if ($buf === false) { 
			// 將client從 clients array中刪除
			$found_socket = array_search($changed_socket, $clients);
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]); //刪除
			
			//發送給client有人斷開連接
			$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
			send_message($response);
		}
	}
}
// 關閉監聽socket
socket_close($socket);

function send_message($msg)
{
	global $clients;
	foreach($clients as $changed_socket)
	{
		@socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}


//解封包
function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}

//編碼訊息後傳給client
function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

//第一次連線時的Handshake
function perform_handshaking($receved_header,$client_conn, $host, $port)
{
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	
	//伺服器回應給client的websocket(Websocket交握請求)
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}
