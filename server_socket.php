<?php

	$socket;
	function asc2bin($temp){
		$len = strlen($temp);
		$bin_data="";
		for($i=0;$i<$len;$i++){
			$bin_data .= sprintf("%08b",ord(substr($temp, $i, 1)));
		}

		return $bin_data;
	}
	function bin2asc($temp){
		$len = strlen($temp);
		$str_data = "";
		for($i=0;$i<$len;$i=$i+8)
			$str_data .= chr(bindec(substr($temp, $i, 8)));
		return $str_data;
	}
	function myXOR($string_a,$string_b){
		$length = strlen($string_a); //default length a=b;
		$result = "";
		for($i=0;$i<$length;$i++){
			if($string_a[$i] == $string_b[$i]){
				$result = $result . "0";
			}
			else
				$result = $result . "1";
		}
		echo $result."\n";
		return $result;
	}	
	function init()
	{
		$tcp = getprotobyname("tcp");
		$socket = socket_create(AF_INET, SOCK_STREAM, $tcp);
		if(!$socket)
			die('error socket');
		socket_bind($socket, '192.168.56.101', 24568);
		socket_listen($socket);
		return $socket;
	}
	
	function handshake($self_socket)
	{
		$client_connection = socket_accept($self_socket);
		$data = socket_read($client_connection,8192);
		printf("Received: \n".$data);
		$accept_keyplace=strpos($data, "Sec-WebSocket-Key")+19;
		$accept_key=substr($data, $accept_keyplace,24);
		$new_key_sha1=sha1($accept_key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",TRUE);
		$new_key=base64_encode($new_key_sha1);
		$response_word="HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nSec-WebSocket-Version: 13\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: "
						.$new_key."\r\n\r\n";
		echo $response_word;
		socket_write($client_connection, $response_word,strlen($response_word));
		return $client_connection;

	}

	function sendMessage($message,$client)
	{
		//syntax:
		//10000001 0[0001111] [data];
		$pre_bin = "100000010";
		$length = strlen($message);
		$len_bin = sprintf("%07b",$length);
		$data = asc2bin($message);
		$encoded_bin = $pre_bin.$len_bin.$data;
		$encoded_message = bin2asc($encoded_bin);
		echo $encoded_message;
		socket_write($client, $encoded_message, strlen($encoded_message));
	}

	function receiveMessage($client){
		$receive = asc2bin(socket_read($client, 1024));//a binary string;
		$whole_length = strlen($receive);
		if($receive[8]==0)
			echo "ERROR FRAME; WARNING: MAYBE SECURITY REASONS";
		else
		{
			$binary_pointer = 16;
			$mask = array();
			$masked_binary = array();
			$final_result = "";
			for($i=0;$i<4;$i++){
				$temp_mask = substr($receive, $binary_pointer, 8);
				$binary_pointer = $binary_pointer + 8;
				array_push($mask, $temp_mask);
			}//save the mask
			while($binary_pointer<$whole_length){
				$temp_masked_binary = substr($receive, $binary_pointer, 8);
				$binary_pointer = $binary_pointer + 8;
				array_push($masked_binary, $temp_masked_binary);
			}//save the masked data;
			$data_length = count($masked_binary);
			for($i=0;$i<$data_length;$i++){
				$mask_place = $i % 4;
				$bin_result = myXOR($mask[$mask_place],$masked_binary[$i]);
				$final_result .= chr(bindec($bin_result));
			}
			
			return $final_result;
		}
		return "ERRORCODE";
	}

	function ping($host, $timeout = 1){
		$package = "\x08\x00\x7d\x4b\x00\x00\x00PingHost";
		$socket = socket_create(AF_INET, SOCK_RAW, 1);
		socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $timeout, 'usec => 0'));
		socket_connect($socket, $host, null);
		$ts = microtime(true);
		socket_send($socket, $package, strlen($package), 0);
		if (socket_read($socket, 255)){
			$result = 1000*(microtime(true) - $ts);
			$result = sprintf("%05f",($result + ""));
		}
		else 
			$result = false;
		return $result;
	}

	function traceroute($dest_addr)
	{
		$maximum_hops = 30;
		$ttl = 1;
		while($ttl < $maximum_hops){
			$recv_socket = socket_create(AF_INET, SOCK_RAW, getprotobyname('icmp'));
			$send_socket = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
			socket_set_option($send_socket, 0, 2, $ttl);
			socket_bind($recv_socket, 0, 0);
			$t1 = microtime(true);
			socket_sendto($send_socket, "", 0, 0, $dest_addr);
			$r =array($recv_socket);
			$w = $e = array();
			socket_select($r, $w, $e, 5, 0);
			if(count($r)){
				socket_recvfrom($recv_socket, $buf, 512, 0, $recv_addr, $recv_port);
				if (empty($recv_addr)){
					$recv_addr = "*";
					$recv_port = "*";
				} else {
					$recv_name = gethostbyaddr($recv_addr);
				}
				printf ("%3d %-15s %.3f ms %s\n", $ttl, $recv_addr, $roundtrip_time, $recv_name);
			} else {
				printf ("%3d (timeout)\n", $ttl);
			}
			socket_close($recv_socket);
			socket_close($send_socket);
			$ttl++;
			if($recv_addr == $dest_addr) break;
		} 
	}
	//MAIN FUNCTION

	$self_socket = init();
	while(true){
		$client = handshake($self_socket);
		$receive = receiveMessage($client);
		echo $receive."\n";
		sendMessage("HELLO",$client);
		sleep(3);
		sendMessage(ping("192.168.56.101"),$client);
		//sendMessage(">hello",$client);
		//socket_close($client);
	}
	
?>
