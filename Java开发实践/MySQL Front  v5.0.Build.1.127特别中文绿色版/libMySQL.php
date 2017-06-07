<?php
	#
	# MySQL-Front Version 5.0.1.133
	# Powered by Nils Hoyer
	# HTTP tunneling script
	# This script is used by the Windows GUI "MySQL-Front" only
	# http://www.mysqlfront.de/
	#
	
	/****************************************************************************/
	
	define("Version", '5.0.0');
	
	define("MIN_COMPRESS_LENGTH", 50);
	define("NET_BUFFER_LENGTH", 8192);
	define("ChunkSize", 2 * NET_BUFFER_LENGTH);
	
	function FlushPackets() {
		global $SendPacketBuffer;
		
		if ($SendPacketBuffer) {
			SendCompressedPacket($SendPacketBuffer);
			$SendPacketBuffer = '';
		}
	}
	
	function PackLength($Length) {
		if ($Length < 0xFB)
			return pack('C', $Length);
		else if ($Length <= 0xFFFF)
			return "\xFC" . pack('v', $Length);
		else if ($Length <= 0xFFFFFF)
			return "\xFD" . substr(pack('V', $Length), 0, 3);
		else
			return "\xFE" . pack('V', $Length) . pack('V', 0);
	}
	
	function ReceivePacket(&$Packet) {
		global $ReadPacketIndex;
		global $UncompressedPackets;
		
		if ($ReadPacketIndex >= strlen($UncompressedPackets))
			return 0;
		else {
			$a = unpack('v', substr($UncompressedPackets, $ReadPacketIndex, 3) . "\x00"); $Size = $a[1]; $ReadPacketIndex += 3;
			$a = unpack('C', substr($UncompressedPackets, $ReadPacketIndex, 1)); $Nr = $a[1]; $ReadPacketIndex += 1;
			
			$Packet = substr($UncompressedPackets, $ReadPacketIndex, $Size); $ReadPacketIndex += $Size;
			
			return 1;
		}
	}
	
	function SendCompressedPacket($Packet) {
		if (strlen($Packet) < MIN_COMPRESS_LENGTH) {
			echo(pack('V', strlen($Packet) & 0xffffff) . "\x00\x00\x00" . $Packet);
		} else {
			$CompressedPacket = gzcompress($Packet);
			echo(pack('V', strlen($CompressedPacket) & 0xffffff) . substr(pack('V', strlen($Packet) & 0xffffff), 0, 3) . $CompressedPacket);
		}
	}
	
	function SendPacket($Packet) {
		global $SendPacketBuffer;
	
		if (! $_POST['compress']) {
			echo(pack('V', strlen($Packet)) . $Packet);
		} else {
			$SendPacketBuffer .= pack('V', strlen($Packet)) . $Packet;
			
			while (strlen($SendPacketBuffer) > ChunkSize) {
				SendCompressedPacket(substr($SendPacketBuffer, 0, ChunkSize));
				$SendPacketBuffer = substr($SendPacketBuffer, ChunkSize);
			}
		}
	}
	
	/****************************************************************************/
	
	error_reporting(E_ERROR | E_PARSE);
	
	foreach(array_keys($_POST) as $Name)
		if ((bool) ini_get('magic_quotes_gpc'))
			$_POST[$Name] = stripslashes($_POST[$Name]);
	$_POST['packets'] = str_replace("\\0", "\x00", $_POST['packets']);
	
	if (! $_POST['compress']) {
		$UncompressedPackets = $_POST['packets'];
	} else {
		$Index = 0;
		while ($Index < strlen($_POST['packets'])) {
			$a = unpack('v', substr($_POST['packets'], $Index, 3) . "\x00"); $Size = $a[1]; $Index += 3;
			$a = unpack('C', substr($_POST['packets'], $Index, 1)); $Nr = $a[1]; $Index += 1;
			$a = unpack('v', substr($_POST['packets'], $Index, 3) . "\x00"); $UncompressedSize = $a[1]; $Index += 3;
			
			if ($UncompressedSize == 0)
				$UncompressedPackets .= substr($_POST['packets'], $Index, $Size); 
			else
				$UncompressedPackets .= gzuncompress(substr($_POST['packets'], $Index, $Size), $UncompressedSize); 
		}
	}
	
	if (isset($_POST['timeout']))
		set_time_limit($_POST['timeout']);
	
	/****************************************************************************/
		
	header('Content-Type: application/MySQL-Front');
	header('Content-Transfer-Encoding: binary');
	
	if ((isset($_POST['library']) and ($_POST['library'] == 'mysql'))
		or ! is_array(get_extension_funcs('mysqli'))
		or ! ($mysqli = mysqli_init())
		or ! mysqli_real_connect($mysqli, $_POST['host'], $_POST['user'], $_POST['password'], $_POST['database'], $_POST['port'], '', (int) $_POST['clientflag'] & ! 0x0020))
	{
		if (version_compare(phpversion(), '4.3.0') < 0)
			$mysql = mysql_connect($_POST['host'] . ':' . $_POST['port'], $_POST['user'], $_POST['password']);
		else
			$mysql = mysql_connect($_POST['host'] . ':' . $_POST['port'], $_POST['user'], $_POST['password'], true, (int) $_POST['clientflag'] & ! 0x0020);
		
		if (! $mysql) {
			$Packet = "\xFF";
			$Packet .= pack('v', mysql_errno());
			$Packet .= mysql_error() . "\x00";
			SendPacket($Packet);
		} else if (mysql_errno($mysql)) {
			$Packet = "\xFF";
			$Packet .= pack('v', mysql_errno($mysql));
			$Packet .= mysql_error($mysql) . "\x00";
			SendPacket($Packet);
		} else {
	 		if ($mysql and (mysql_errno($mysql) == 0) and $_POST['database'])
				mysql_select_db($_POST['database'], $mysql);
			if ($mysql and $_POST['charset'] and version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysql)), '4.1.1') >= 0)
				if ((version_compare(phpversion(), '5.2.3') < 0) or version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysql)), '5.0.7'))
					mysql_query("SET NAMES " . $_POST['charset'] . ";", $mysql);
				else
					mysql_set_charset($_POST['charset'], $mysql);
			if ($mysql and $_POST['charset'])
			{
				if (version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysql)), "5.0.0") >= 0)
				{
					$result = mysql_query("SELECT `MAXLEN` FROM `information_schema`.`CHARACTER_SETS` WHERE `CHARACTER_SET_NAME`='" . $_POST['charset'] . "';");
					if (($Row = mysql_fetch_array($result, MYSQL_NUM)) and $Row[0])
						$MBCLen = (int) $Row[0] . "\n";
				}
				else if (version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysql)), "4.1.0") >= 0)
				{
					$result = mysql_query("SHOW CHARACTER SET LIKE '" . $charset . "';");
					if (($Row = mysql_fetch_array($result, MYSQL_NUM)) and $Row[3])
						$MBCLen = (int) $Row[3] . "\n";
				}
			}
			if (! isset ($MBCLen) or ($MBCLen == 0))
				$MBCLen = 1;
			
			while (ReceivePacket($Packet))
				if (substr($Packet, 0, 1) == "\x0B") { // COM_CONNECT
					$Packet = "";
					$Packet .= "Tunnel-Info=" . Version . "\n";
					$Packet .= "Client-Info=" . mysql_get_client_info() . " mysql\n";
					$Packet .= "Host-Info=" . mysql_get_host_info($mysql) . "\n";
					if (version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysql)), "4.1.1") < 0) {
						$result = mysql_query("SHOW VARIABLES LIKE 'character_set';", $mysql);
						if (($Row = mysql_fetch_array($result, MYSQL_NUM)) and $Row[1])
							$Packet .= "Character-Set-Name=" . $Row[1] . "\n";
					} else if ((version_compare(phpversion(), '5.2.3') < 0) or version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysql)), '5.0.7')) {
						$result = mysql_query("SHOW VARIABLES LIKE 'character_set_client';", $mysql);
						if (($Row = mysql_fetch_array($result, MYSQL_NUM)) and $Row[1])
							$Packet .= "Character-Set-Name=" . $Row[1] . "\n";
					} else
						$Packet .= "Character-Set-Name=" . mysql_client_encoding($mysql) . "\n";
					$Packet = PackLength(strlen($Packet)) . $Packet;
					SendPacket($Packet);
					
					$Packet = "";
					$Packet .= pack('C', 10); // Protocol
					$Packet .= mysql_get_server_info($mysql) . "\x00";
					$Packet .= pack('V', 0); // Thread Id
					$Packet .= "00000000\x00"; // Salt
					if (($_POST['clientflag'] & 0x0020) and function_exists('gzcompress'))
						$Packet .= pack('v', 0x422C); // Server Capabilities
					else
						$Packet .= pack('v', 0x420C); // Server Capabilities
					$Packet .= pack('C', 0); // Charset Nr
					$Packet .= pack('v', 0x0002); // Server Status
					$Packet .= pack('a13', 1); // unused
					SendPacket($Packet);
					
					$Packet = "";
					$Packet .= pack('C', 0);
					$Packet .= PackLength(0); // Affected Rows
					$Packet .= PackLength(0); // Insert Id
					$Packet .= pack('v', 0x0002); // Server Status
					$Packet .= pack('v', 0x0000); // Warning Count
					SendPacket($Packet);
				} else if (substr($Packet, 0, 1) == "\x03") { // COM_QUERY
					$Query = substr($Packet, 1); 
					$result = mysql_query($Query, $mysql);
					
					if (mysql_errno($mysql)) {
						$Packet = "\xFF";
						$Packet .= pack('v', mysql_errno($mysql));
						$Packet .= mysql_error($mysql) . "\x00";
						SendPacket($Packet);
						break;
					} else if (! mysql_num_fields($result)) {
						$Packet = "\x00";
						$Packet .= PackLength(mysql_affected_rows($mysql));
						$Packet .= PackLength(mysql_insert_id($mysql));
						if ($ReadPacketIndex < strlen($UncompressedPackets))
							$Packet .= pack('v', 8); // Server Status
						else
							$Packet .= pack('v', 0); // Server Status
						$Packet .= pack('v', 0); // WarningCount
						if ((version_compare(phpversion(), '4.3.0') < 0) or ! mysql_info($mysql))
							$Packet .= "\xFB";
						else
							$Packet .= PackLength(strlen(mysql_info($mysql))) . mysql_info($mysql);
						SendPacket($Packet);
					} else {
						$Packet = PackLength(mysql_num_fields($result));
						SendPacket($Packet);
						
						for ($i = 0; $i < mysql_num_fields($result); $i++) {
							$Field = mysql_fetch_field($result, $i);
							
							$Flags = 0;
							$Length = mysql_field_len($result, $i);
							if (mysql_field_type($result, $i) == 'string')
								$Length = $Length / $MBCLen;
							$MaxLength = max($Length, $Field->max_length);
							switch (mysql_field_type($result, $i)) {
								case "int":
									     if ($MaxLength <  3)    $FieldType =   1;
									else if ($MaxLength <  5)    $FieldType =   2;
									else if ($MaxLength <  7)    $FieldType =   9;
									else if ($MaxLength < 10)    $FieldType =   3;
									else                         $FieldType =   8; break;
								case "float":                  $FieldType =   4; break;
								case "real":                   $FieldType =   5; break;
								case "null":                   $FieldType =   6; break;
								case "timestamp":              $FieldType =   7; break;
								case "date":                   $FieldType =  10; break;
								case "time":                   $FieldType =  11; break;
								case "datetime":               $FieldType =  12; break;
								case "year":                   $FieldType =  13; break;
								case "bit":                    $FieldType =  16; break;
								case "blob": {
									$Flags |= 0x00010;
									     if ($Length < 0xff    ) $FieldType = 249;
									else if ($Length < 0xffff  ) $FieldType = 252;
									else if ($Length < 0xffffff) $FieldType = 250;
									else                         $FieldType = 251;
								}                                                break;
								case "string":                 $FieldType = 254; break;
								default:                       $FieldType =   0;
							}
							foreach (explode(" ", trim(mysql_field_flags($result, $i))) as $Flag)
								switch ($Flag) {
									case "not_null":       $Flags |= 0x00001; break;
									case "primary_key":    $Flags |= 0x00002; break;
									case "unique_key":     $Flags |= 0x00004; break;
									case "multiple_key":   $Flags |= 0x00008; break;
									case "blob":           $Flags |= 0x00010; break;
									case "unsigned":       $Flags |= 0x00020; break;
									case "zerofill":       $Flags |= 0x00040; break;
									case "binary":         $Flags |= 0x00080; break;
									case "enum":           $Flags |= 0x00100; break;
									case "auto_increment": $Flags |= 0x00200; break;
									case "timestamp":      $Flags |= 0x00400; break;
								}
							if (($FieldType == 4) or ($FieldType == 5)) {
								while (($Row = mysql_fetch_array($result, MYSQL_NUM)) and ! isset($Row[$i])) ;
								if (isset($Row))
									$Decimals = StrLen($Row[$i]) - StrPos($Row[$i], ".") - 1;
								mysql_data_seek($result, 0);
							}
							
							$Packet = "";
							$Packet .= "\xFB"; // catalog
							if (! isset($Field->db))
								$Packet .= "\xFB";
							else
								$Packet .= pack('C', strlen($Field->db)) . $Field->db;
							if (! isset($Field->table))
								$Packet .= "\xFB";
							else
								$Packet .= pack('C', strlen($Field->table)) . $Field->table;
							if (! isset($Field->org_table))
								$Packet .= "\xFB";
							else
								$Packet .= pack('C', strlen($Field->org_table)) . $Field->org_table;
							if (! isset($Field->name))
								$Packet .= "\xFB";
							else
								$Packet .= pack('C', strlen($Field->name)) . $Field->name;
							if (! isset($Field->org_name))
								$Packet .= "\xFB";
							else
								$Packet .= pack('C', strlen($Field->org_name)) . $Field->org_name;
							$Packet .= "\x0A";
							$Packet .= pack('v', 0);
							$Packet .= pack('V', $Length);
							$Packet .= pack('C', $FieldType);
							$Packet .= pack('v', $Flags);
							$Packet .= pack('C', $Decimals);
							SendPacket($Packet);
						}
						$Packet = "";
						$Packet .= "\xFE";
						$Packet .= pack('v', 0); // WarningCount
						if ($ReadPacketIndex < strlen($UncompressedPackets))
							$Packet .= pack('v', 8); // Server Status
						else
							$Packet .= pack('v', 0); // Server Status
						SendPacket($Packet);
						FlushPackets();
	
						while ($Row = mysql_fetch_array($result, MYSQL_NUM)) {
							$Packet = "";
							$Lengths = mysql_fetch_lengths($result);
							for ($i = 0; $i < mysql_num_fields($result); $i++) {
								if (! isset($Row[$i]))
									$Packet .= "\xFB";
								else
									$Packet .= PackLength($Lengths[$i]);
								$Packet .= $Row[$i];
							}
							SendPacket($Packet);
						}
						$Packet = "";
						$Packet .= "\xFE";
						$Packet .= pack('v', 0); // WarningCount
						if ($ReadPacketIndex < strlen($UncompressedPackets))
							$Packet .= pack('v', 8); // Server Status
						else
							$Packet .= pack('v', 0); // Server Status
						SendPacket($Packet);
						FlushPackets();
					}
				}
		}
		
		mysql_close($mysql);
	
	} else { /*******************************************************************/
	
		if (! mysqli_errno($mysqli) and $_POST['charset']) {
			if ((version_compare(phpversion(), '5.2.3') < 0) or (mysql_get_server_info($mysqli) and version_compare(ereg_replace("-.*$", "", mysql_get_server_info($mysqli)), '5.0.7')))
				mysqli_query($mysqli, "SET NAMES " . $_POST['charset'] . ";");
			else
				mysqli_set_charset($mysqli, $_POST['charset']);
		}
		
		if (mysql_errno($mysql)) {
			$Packet = "\xFF";
			$Packet .= pack('v', mysqli_errno($mysqli));
			$Packet .= mysqli_error($mysqli) . "\x00";
			SendPacket($Packet);
		} else {
			while (ReceivePacket($Packet))
				if (substr($Packet, 0, 1) == "\x0B") { // COM_CONNECT
					$Packet = "";
					$Packet .= "Tunnel-Info=" . Version . "\n";
					$Packet .= "Client-Info=" . mysqli_get_client_info() . " mysqli\n";
					$Packet .= "Host-Info=" . mysqli_get_host_info($mysqli) . "\n";
					$Packet .= "Character-Set-Name=" . mysqli_character_set_name($mysqli) . "\n";
					$Packet = PackLength(strlen($Packet)) . $Packet;
					SendPacket($Packet);
					
					$Packet = "";
					$Packet .= pack('C', 10); // Protocol
					$Packet .= mysqli_get_server_info($mysqli) . "\x00";
					$Packet .= pack('V', 0); // Thread Id
					$Packet .= "00000000\x00"; // Salt
					if (($_POST['clientflag'] & 0x0020) and function_exists('gzcompress'))
						$Packet .= pack('v', 0x422C); // Server Capabilities
					else
						$Packet .= pack('v', 0x420C); // Server Capabilities
					$Packet .= pack('C', 0); // Charset Nr
			 		$Packet .= pack('v', 0x0002); // Server Status
					$Packet .= pack('a13', 1); // unused
					SendPacket($Packet);
					
					$Packet = "";
					$Packet .= pack('C', 0);
					$Packet .= PackLength(0); // Affected Rows
					$Packet .= PackLength(0); // Insert Id
					$Packet .= pack('v', 0x0002); // Server Status
					$Packet .= pack('v', 0x0000); // Warning Count
					SendPacket($Packet);
				} else if (substr($Packet, 0, 1) == "\x03") { // COM_QUERY
					$Query = substr($Packet, 1); 
					mysqli_multi_query($mysqli, $Query);
					
					if (mysqli_errno($mysqli)) {
						$Packet = "\xFF";
						$Packet .= pack('v', mysqli_errno($mysqli));
						$Packet .= mysqli_error($mysqli) . "\x00";
						SendPacket($Packet);
						break;
					}	else if (eregi("^USE ", $Query)) {
						// on some PHP versions mysqli_use_result just ignores "USE Database;"
						// statements. So it has to be handled separately:
						$Packet = "";
						$Packet .= PackLength(mysqli_num_fields($result));
						$Packet .= PackLength(mysqli_affected_rows($mysqli));
						$Packet .= PackLength(mysql_insert_id($mysql));
						if ($q < $_POST['query_count'] - 1)
							$Packet .= pack('v', 8); // Server Status
						else
							$Packet .= pack('v', 0); // Server Status
						$Packet .= pack('v', mysqli_warning_count($mysqli));
						if ((version_compare(phpversion(), '4.3.0') < 0) or ! mysqli_info($mysqli))
							$Packet .= "\xFB";
						else
							$Packet .= PackLength(strlen(mysqli_info($mysqli))) . mysqli_info($mysqli);
						SendPacket($Packet);
					} else {
						do {
							$result = mysqli_use_result($mysqli);
							
							if (mysqli_errno($mysqli)) {
								$Packet = "\xFF";
								$Packet .= pack('v', mysqli_errno($mysqli));
								$Packet .= mysqli_error($mysqli) . "\x00";
								SendPacket($Packet);
								break 2;
							}	else if (! $result) {
								$Packet = "\x00";
								$Packet .= PackLength(mysqli_affected_rows($mysqli));
								$Packet .= PackLength(mysql_insert_id($mysql));
								if ($ReadPacketIndex < strlen($UncompressedPackets))
									$Packet .= pack('v', 8); // Server Status
								else
									$Packet .= pack('v', 0); // Server Status
								$Packet .= pack('v', mysqli_warning_count($mysqli));
								if ((version_compare(phpversion(), '4.3.0') < 0) or ! mysqli_info($mysqli))
									$Packet .= "\xFB";
								else
									$Packet .= PackLength(strlen(mysqli_info($mysqli))) . mysqli_info($mysqli);
								SendPacket($Packet);
							} else {
								$Packet = PackLength(mysqli_num_fields($result));
								SendPacket($Packet);
								
								while ($Field = mysqli_fetch_field($result)) {
									$Packet = "";
									if (! isset($Field->catalog))
										$Packet .= "\xFB";
									else
										$Packet .= pack('C', strlen($Field->catalog)) . $Field->catalog;
									if (! isset($Field->db))
										$Packet .= "\xFB";
									else
										$Packet .= pack('C', strlen($Field->db)) . $Field->db;
									if (! isset($Field->table))
										$Packet .= "\xFB";
									else
										$Packet .= pack('C', strlen($Field->table)) . $Field->table;
									if (! isset($Field->org_table))
										$Packet .= "\xFB";
									else
										$Packet .= pack('C', strlen($Field->org_table)) . $Field->org_table;
									if (! isset($Field->name))
										$Packet .= "\xFB";
									else
										$Packet .= pack('C', strlen($Field->name)) . $Field->name;
									if (! isset($Field->org_name))
										$Packet .= "\xFB";
									else
										$Packet .= pack('C', strlen($Field->org_name)) . $Field->org_name;
									$Packet .= "\x0A";
									$Packet .= pack('v', $Field->charsetnr);
									$Packet .= pack('V', $Field->length);
									$Packet .= pack('C', $Field->type);
									$Packet .= pack('v', $Field->flags);
									$Packet .= pack('C', $Field->decimals);
									SendPacket($Packet);
								}
								$Packet = "";
								$Packet .= "\xFE";
								$Packet .= pack('v', mysqli_warning_count($mysqli));
								if ($ReadPacketIndex < strlen($UncompressedPackets))
									$Packet .= pack('v', 8); // Server Status
								else
									$Packet .= pack('v', 0); // Server Status
								SendPacket($Packet);
								FlushPackets();
								
								while ($Row = mysqli_fetch_array($result, MYSQL_NUM)) {
									$Packet = "";
									$Lengths = mysqli_fetch_lengths($result);
									for ($i = 0; $i < mysqli_num_fields($result); $i++) {
										if (! isset($Row[$i]))
											$Packet .= "\xFB";
										else
											$Packet .= PackLength($Lengths[$i]);
										$Packet .= $Row[$i];
									}
									SendPacket($Packet);
								}
								$Packet = "";
								$Packet .= "\xFE";
								$Packet .= pack('v', mysqli_warning_count($mysqli));
								if ($ReadPacketIndex < strlen($UncompressedPackets))
									$Packet .= pack('v', 8); // Server Status
								else
									$Packet .= pack('v', 0); // Server Status
								SendPacket($Packet);
								FlushPackets();

								mysqli_free_result($result);
							}
						
						} while (mysqli_next_result($mysqli));
					}
				}
		}

		mysqli_close($mysqli);
	}
	
	FlushPackets();
?>
