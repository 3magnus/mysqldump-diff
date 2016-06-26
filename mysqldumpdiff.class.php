<?php

/**
 * @file
 * This class takes two data only MySQL backups (dumps with no table structure changes)
 * and puts the differences between them in a third file, generating a data update file.
 * Script takes into account the format from Backup & Migrate module for Drupal 7.
 */

class MySQLdumpDiff {
	public $File1;
	public $File2;
	public $File3;
	public $Options;
	public $Export;
	private $FileDesc;


	function MySQLdumpDiff() {
		$this->FileDesc;
	}

	
	function ProcessFiles() {
		$File1Array = $this->AssureOneQueryPerLine(file($this->File1));
		$File2Array = $this->AssureOneQueryPerLine(file($this->File2));
		$File3Array = array();
		$DeletesArray = array();
		$Result = array('Repeats' => 0, 'UPDATES' => 0, 'DELETES' => 0, 'INSERTS' => 0);
		$ExtResult = array();

		foreach ($File1Array['queries'] as $Key1=>$Value1) 	{
			$Begin = substr($Value1, 0, 11);
			if ($Begin == "CREATE TABL") {
				$NextTable = $this->GetNextTableStructure($Value1); 
			}
			elseif ($Begin == "INSERT INTO") {
				$Table1 = $this->GetTableName($Value1);
				$InsertArray1 = $this->GetSeparateInserts($Value1);
				$CorrespKey = array_keys($File2Array['tables'], $Table1);
			 	if (!empty($CorrespKey)) {
					foreach ($InsertArray1 as $Key11=>$Value11) {
						$Searching = TRUE;
						reset($CorrespKey);  
						$CurrKey = current($CorrespKey);
						while ($Searching && $CurrKey !== FALSE) {
							$InsertArray2 = $this->GetSeparateInserts($File2Array['queries'][$CurrKey]);
							$FullMatch = -1;
							$Value2 = current($InsertArray2);
							while (($FullMatch < 0) && ($Value2 !== FALSE)) {
								if ($Value11 == $Value2) {
									$FullMatch = key($InsertArray2);
								}
								$Value2 = next($InsertArray2);
							}
							if ($FullMatch > -1) {
								unset($InsertArray2[$FullMatch]);
								$Result['Repeats']++;
								if (isset($ExtResult[$Table1]['Repeats'])): $ExtResult[$Table1]['Repeats']++; else: $ExtResult[$Table1]['Repeats'] = 1; endif;
								$Searching = FALSE;
							}
							$CurrKey = next($CorrespKey);
						}
						if ($Searching) {
							reset($CorrespKey);  
							$CurrKey = current($CorrespKey);
							while ($Searching && $CurrKey !== FALSE) {
								$InsertArray2 = $this->GetSeparateInserts($File2Array['queries'][$CurrKey]);
								$UniqueValue = $this->GetRowIdentifier($Table1, $NextTable, $Value11, "string");
								$MatchesArray = array_filter($InsertArray2, function($Values) use ($UniqueValue) {
     								return (strpos($Values, $UniqueValue) !== FALSE);
  								});
								if (!empty($MatchesArray)) {
									if (count($MatchesArray) > 1) {
										die("ERROR IN ".$Table1." AND ".$UniqueValue);
									}
									else {
										$RowChanges = $this->GetRowChangesSQL($NextTable, $Value11, current($MatchesArray));
										$RowId = $this->GetRowIdentifier($Table1, $NextTable, $Value11, "update");
										$DeletesArray[] = "UPDATE `".$Table1."` SET".$RowChanges." WHERE ".$RowId.";";
										$Result['UPDATES']++;
										if (isset($ExtResult[$Table1]['UPDATES'])): $ExtResult[$Table1]['UPDATES']++; else: $ExtResult[$Table1]['UPDATES'] = 1; endif;
										$Searching = FALSE;
									}
								}
								$CurrKey = next($CorrespKey);
							}
							if ($Searching) {
								$RowId = $this->GetRowIdentifier($Table1, $NextTable, $Value11, "update");
								$DeletesArray[] = "DELETE FROM `".$Table1."` WHERE ".$RowId.";";
								$Result['DELETES']++;
								if (isset($ExtResult[$Table1]['DELETES'])): $ExtResult[$Table1]['DELETES']++; else: $ExtResult[$Table1]['DELETES'] = 1; endif;
							}
						}
					}
				}
				else {
					foreach ($InsertArray1 as $Key=>$Value) {
						$RowId = $this->GetRowIdentifier($Table1, $NextTable, $Value, "update");
						$DeletesArray[] = "DELETE FROM `".$Table1."` WHERE ".$RowId.";";
						$Result['DELETES']++;
						if (isset($ExtResult[$Table1]['DELETES'])): $ExtResult[$Table1]['DELETES']++; else: $ExtResult[$Table1]['DELETES'] = 1; endif;
					}
				}
			}
		}

		$PrintDeletes = TRUE;
		foreach ($File2Array['queries'] as $Key2=>$Value2) {
			$Begin = substr($Value2, 0, 11);
			if (($Value2 == "") || ($Value2 == "--") || ($Begin == "DROP TABLE ") || ($Begin == "-- Table st") || ($Begin == "-- Dumping ")) {
				// No empty lines, no commented empty lines, no droping, no obvious comments
			}
			elseif ($Begin == "CREATE TABL") {
				$NextTable = $this->GetNextTableStructure($Value2);
				if ($PrintDeletes) {
					foreach ($DeletesArray as $item) {
						$File3Array[] = $item;
					}
					$PrintDeletes = FALSE;
					unset($DeletesArray);
				}
				if ($this->Options[1] == 1) {
					$File3Array[] = $Value2;
				}
			}
			elseif ($Begin == "INSERT INTO") {
				$Table2 = $this->GetTableName($Value2);
				$InsertArray2 = $this->GetSeparateInserts($Value2);
				$CorrespKey = array_keys($File1Array['tables'], $Table2);
			 	if (!empty($CorrespKey)) {
			 		$ValuesArray = array();
					foreach ($InsertArray2 as $Key22=>$Value22) {
						$Searching = TRUE;
						reset($CorrespKey);  
						$CurrKey = current($CorrespKey);
						while ($Searching && $CurrKey !== FALSE) {
							$InsertArray1 = $this->GetSeparateInserts($File1Array['queries'][$CurrKey]);
							$FullMatch = -1;
							$Value1 = current($InsertArray1);
							while (($FullMatch < 0) && ($Value1 !== FALSE)) {
								if ($Value22 == $Value1) {
									$FullMatch = key($InsertArray1);
								}
								$Value1 = next($InsertArray1);
							}
							if ($FullMatch > -1) {
								//unset($InsertArray1[$FullMatch]);
								$Searching = FALSE;
							}
							$CurrKey = next($CorrespKey);
						}
						if ($Searching) {
							reset($CorrespKey);  
							$CurrKey = current($CorrespKey);
							while ($Searching && $CurrKey !== FALSE) {
								$InsertArray1 = $this->GetSeparateInserts($File1Array['queries'][$CurrKey]);
								$UniqueValue = $this->GetRowIdentifier($Table2, $NextTable, $Value22, "string");
								$MatchesArray = array_filter($InsertArray1, function($Values) use ($UniqueValue) {
      	  						return (strpos($Values, $UniqueValue) !== FALSE);
    							});
								if (empty($MatchesArray)) 	{
									$ValuesArray[] = $Value22;
									$Searching = FALSE;
								}
								$CurrKey = next($CorrespKey);
							}
						}
				 	}
				 	if ($Searching && !empty($ValuesArray)) {
				 		$num = count($ValuesArray);
						$Result['INSERTS'] += $num;
						if (isset($ExtResult[$Table2]['INSERTS'])): $ExtResult[$Table2]['INSERTS'] += $num; else: $ExtResult[$Table2]['INSERTS'] = $num; endif;
						$i = 0;
						while ($i < $num) {
							$Slice = array_slice($ValuesArray, $i, 200); 
							$File3Array[] = "INSERT INTO `".$Table2."` VALUES ".implode(",", $Slice).";";
							$i += 200;
						}
					}
			 		//unset($File1Array['tables'][$CorrespKey]);
				}
				else {
					$num = count($InsertArray2);
					$Result['INSERTS'] += $num;
					if (isset($ExtResult[$Table2]['INSERTS'])): $ExtResult[$Table2]['INSERTS'] += $num; else: $ExtResult[$Table2]['INSERTS'] = $num; endif;
					$File3Array[] = $Value2;
				}
			}
			else {
				$File3Array[] = $Value2;
			}
		}

		$DiffArray = $this->GetHeaderText($Result, $ExtResult);
		$File3Array = array_merge($File2Array['header'], $DiffArray, $File3Array);
		if ($this->Export == 'file') {
			$this->OpenFile();
			$this->WriteArrayToFile($File3Array);
		}
		else {
			$output = "";
			$rows = 0;
			foreach ($File3Array as $item) {
				$output .= $item."\n";
				$rows++;
			}
			print '<label for="diff-export">Diff SQL:</label><textarea id="diff-export" name="diff-sql" cols="60" rows="'.$rows.'">
'.$output.'
</textarea>';
		}
	}


	function AssureOneQueryPerLine($FArray) {
		$NewKey = 0;
		$OutArray = array(
			'tables' => array(),
			'header' => array(),
			'queries' => array()
		);
		$GatheringQuery = FALSE;
		foreach ($FArray as $Key=>$Value) {
			$Value = trim($Value);
			$Begin = substr($Value, 0, 11);
			if (($Value == "") || ($Value == "--") || ($Value == "-- --------------------------------------------------------") || ($Begin == "DROP TABLE ") || ($Begin == "-- Database") || ($Begin == "-- Table st") || ($Begin == "-- Dumping ")) {
				// No empty lines, no commented empty lines, no separators, no droping, no obvious comments
			}
			elseif (substr($Value, 0, 3) == "-- ") {
				$OutArray['header'][] = $Value;
			}
			elseif ($GatheringQuery) {
				$QueryString .= $Value;
				$End = substr($Value, -1, 1);
				if ($End == ";") {
					$OutArray['queries'][$NewKey] = $QueryString;
					$NewKey++;
					$GatheringQuery = FALSE;
				}
			}
			else {
				$End = substr($Value, -1, 1);
				if (substr($Value, 0, 11) == "CREATE TABL") {
					$QueryString = str_replace("CREATE TABLE `", "CREATE TABLE IF NOT EXISTS `", $Value);
					if ($End == ";") {
						$OutArray['queries'][$NewKey] = $QueryString;
						$NewKey++;
					}
					else {
						$GatheringQuery = TRUE;
					}
				}
				elseif (substr($Value, 0, 11) == "INSERT INTO") {
					$QueryString = $Value;
					$OutArray['tables'][$NewKey] = $this->GetTableName($Value);
					if ($End == ";") {
						$OutArray['queries'][$NewKey] = $QueryString;
						$NewKey++;
					}
					else {
						$GatheringQuery = TRUE;
					}
				}
			}
		}
		return $OutArray;
	}
	

	function GetTableName($QLine) {
		$pos = strpos($QLine, "` (`"); // phpMyAdmin3.4 notation
		if ($pos === FALSE) {
			$pos = strpos($QLine, "` VALUES"); // Backup & Migrate for Drupal 7 notation
			if ($pos === FALSE) {
				$pos = strpos($QLine, "' VALUES");
				if ($pos === FALSE) {
					die("ERROR");
				}
			}
		}
		return substr($QLine, 13, $pos-13);
	}	


	function GetNextTableStructure($QLine) {
		$TableArray = array(
			'Fields' => array(),
			'Primary' => array()
		);
		$PrimKeyPos = strpos($QLine, "PRIMARY KEY (");
		$ColsIni = strpos($QLine, "` (");
		$CleanValue = trim(substr($QLine, $ColsIni+3, $PrimKeyPos));
		$ColumnsArray = explode("`", $CleanValue);
		$IsOdd = FALSE;
		foreach ($ColumnsArray as $item) {
			if ($IsOdd) {
				$TableArray['Fields'][] = $item;
			}
			$IsOdd = !$IsOdd;
		}
		$PrimKeyEnd = strpos($QLine, ")", $PrimKeyPos);
		$CleanValue = substr($QLine, $PrimKeyPos+13, $PrimKeyEnd-$PrimKeyPos-13);
		$CleanValue = trim(str_replace("`", "", $CleanValue));
		$PrimaryArray = explode(",", $CleanValue);
		foreach ($PrimaryArray as $item) {
			$PrimKey = array_search($item, $TableArray['Fields']);
			if ($PrimKey !== FALSE) {
				$TableArray['Primary'][$PrimKey] = $item;
			}
		}
		return $TableArray;
	}


	function GetSeparateInserts($QLine) {
		$QLine = substr($QLine, 0, -1);
		if (strpos($QLine, ") VALUES") !== FALSE) {
			$QueryParts = explode(") VALUES", $QLine);
		}
		elseif (strpos($QLine, "` VALUES ") !== FALSE) {
			$QueryParts = explode("` VALUES ", $QLine);
		}
		else {
			die("Unknown INSERT format");			
		}
		$QueryParts[1] = str_replace("),(", ")#}{#(", $QueryParts[1]);
		return explode("#}{#", $QueryParts[1]);
	}
	
	
	function GetRowIdentifier($Table, $TableArray, $Line, $Type) {
		$ReturnString = "";
		$ValParts = explode(",",$Line);
		foreach ($TableArray['Primary'] as $Key=>$Value) {
			if ($Type == "update") {
				if ($Key == 0) {
					$ValParts[0] = substr($ValParts[0], 1, strlen($ValParts[0])-1);
				}
				$ReturnString .= "`".$Table."`.`".$Value."` = ".$ValParts[$Key]." AND "; 
			}
			else {
				$ReturnString .= $ValParts[$Key].",";
			}
		}
		if ($Type == "update") {
			return substr($ReturnString, 0, -5); 
		}
		else {
			return str_replace("),", ")", $ReturnString); 
		}
	}

	
	function GetRowChangesSQL($TableArray, $ValuesFrom1, $ValuesFrom2) {
		$CleanValue = substr(trim($ValuesFrom1), 0, -1);
		$ValuesArray1 = explode(",",$CleanValue);
		$CleanValue = substr(trim($ValuesFrom2), 0, -1);
		$ValuesArray2 = explode(",",$CleanValue);
		$ChangesArray = array();
		foreach ($ValuesArray2 as $Key=>$Value) {
			if ($Value != $ValuesArray1[$Key]) 	{
				$ChangesArray[$Key] = $Value;
			}
		} 
		$ChangesSQL = "";
		foreach ($ChangesArray as $Key=>$Value) {
			$ChangesSQL .= " `".$TableArray['Fields'][$Key]."` = ".$Value.",";
		}
		return substr($ChangesSQL, 0, -1);
	}
	

	function GetHeaderText($ResultArray, $ExtResultArray) {
		$ResultString = "";
		foreach ($ResultArray as $Key=>$Value) {
			$ResultString .= " &nbsp; ".$Key."=".$Value;
		}
		$HeaderArray[] = "-- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -";
		$HeaderArray[] = "-- File1: ".$this->File1;
		$HeaderArray[] = "-- File2: ".$this->File2;
		$HeaderArray[] = "-- Diff:".$ResultString;
		$HeaderArray[] = "-- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -";
		if ($this->Options[0] == 1) {
			foreach ($ExtResultArray as $Key=>$Value) {
				$ResArr = print_r($ExtResultArray[$Key], TRUE);
				$ResArr = str_replace("Array", "", $ResArr);
				$HeaderArray[] = "-- ".$Key." &nbsp;".$ResArr;
			}
			$HeaderArray[] = "-- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -";
		}
		return $HeaderArray;
	}


	function OpenFile()
	{
		$this->FileDesc = fopen($this->File3,"w");
	}


	function WriteArrayToFile($FArray)
	{
		foreach ($FArray as $item)
		{
			fwrite($this->FileDesc, $item);
		}
		fclose($this->FileDesc);
		print "<p>Differences saved in: <strong>".$this->File3."</strong>";
	}

}