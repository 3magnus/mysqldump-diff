<?php

/**
 * @file
 * This class takes two data only MySQL backups (dumps with no table structure changes)
 * and puts the differences between them in a third file, generating a data update file.
 * Script takes into account the format from Backup & Migrate module for Drupal 7.
 */

class MySQLdumpDiff
{
	public $File1;
	public $File2;
	public $File3;
	public $Export;
	private $FileDesc;

	function MySQLdumpDiff()
	{
		$this->FileDesc;
	}
	
	function ProcessFiles()
	{
		$FArray1 = file($this->File1);
		$AllTables1 = $this->GetAllTables($FArray1);
		$FArray2 = file($this->File2);
		$AllTables2 = $this->GetAllTables($FArray2);
		$FArray3 = array();
		$DeletesArray = array();

		foreach ($FArray1 as $Key1=>$Value1)
		{
			$begin = substr($Value1, 0, 11);
			if ($begin == "CREATE TABL")
			{
				$Value1 = str_replace("CREATE TABLE `", "CREATE TABLE IF NOT EXISTS `", $Value1);
				$NextTable = $this->GetNextTableStructure($Value1);
			}
			elseif ($begin == "INSERT INTO")
			{
				$Table1 = $this->GetTableName($Value1);
				$InsertArray1 = $this->GetSeparateInserts($Value1);
				$CorrespKey = array_search($Table1, $AllTables2);
			 	if ($CorrespKey !== FALSE)
			 	{
					$InsertArray2 = $this->GetSeparateInserts($FArray2[$CorrespKey]);
					
					foreach ($InsertArray1 as $Key=>$Value)
					{
						$FullMatchKey = array_search($Value, $InsertArray2);
						if ($FullMatchKey !== FALSE)				
						{
							unset($InsertArray2[$FullMatchkey]);
						}
						else 
						{
							$UniqueValue = $this->GetRowIdentifier($Table1, $NextTable, $Value, "string");
							$MatchesArray2 = array_filter($InsertArray2, function($Values) use ($UniqueValue) {
        						return (strpos($Values, $UniqueValue) !== FALSE);
    						});
    
							if (!empty($MatchesArray2))
							{
								if (count($MatchesArray2) > 1) 
								{
									die("ERROR IN ".$Table1." AND ".$UniqueValue);
								}
								else
								{
									$RowChanges = $this->GetRowChangesSQL($NextTable, $Value, current($MatchesArray2));
									$RowId = $this->GetRowIdentifier($Table1, $NextTable, $Value, "update");
									$DeletesArray[] = "UPDATE `".$Table1."` SET".$RowChanges." WHERE ".$RowId.";";
								}
							}
							else
							{
								$RowId = $this->GetRowIdentifier($Table1, $NextTable, $Value, "update");
								$DeletesArray[] = "DELETE FROM `".$Table1."` WHERE ".$RowId.";";
							}
						}
					}
				}
				else
				{
					foreach ($InsertArray1 as $Key=>$Value)
					{
						$RowId = $this->GetRowIdentifier($Table1, $NextTable, $Value, "update");
						$DeletesArray[] = "DELETE FROM `".$Table1."` WHERE ".$RowId.";";
					}
				}
			}
		}

		$PrintDeletes = TRUE;
		foreach ($FArray2 as $Key2=>$Value2)
		{
			$begin = substr($Value2, 0, 11);
			if ($begin == "DROP TABLE ")
			{
				// No droping			
			}
			elseif ($begin == "CREATE TABL")
			{
				$Value2 = str_replace("CREATE TABLE `", "CREATE TABLE IF NOT EXISTS `", $Value2);
				$NextTable = $this->GetNextTableStructure($Value2);

				if ($PrintDeletes)
				{
					foreach ($DeletesArray as $item)
					{
						$FArray3[] = $item;
					}
					$PrintDeletes = FALSE;
				}

				$FArray3[] = $Value2;
			}
			elseif ($begin == "INSERT INTO")
			{
				$Table2 = $this->GetTableName($Value2);
				$CorrespKey = array_search($Table2, $AllTables1);
			 	if ($CorrespKey !== FALSE)
			 	{
			 		$ValuesArray = array();
					$InsertArray2 = $this->GetSeparateInserts($Value2);
					$InsertArray1 = $this->GetSeparateInserts($FArray1[$CorrespKey]);
					
					foreach ($InsertArray2 as $Key=>$Value)
					{
						$FullMatchKey = array_search($Value, $InsertArray1);
						if ($FullMatchKey !== FALSE)				
						{
							unset($InsertArray1[$FullMatchkey]);
						}
						else 
						{
							$UniqueValue = $this->GetRowIdentifier($Table2, $NextTable, $Value, "string");
							$MatchesArray1 = array_filter($InsertArray1, function($Values) use ($UniqueValue) {
        						return (strpos($Values, $UniqueValue) !== FALSE);
    						});
    
							if (empty($MatchesArray1))
							{
								$ValuesArray[] = $Value;
							}
						}
					}
					$FArray3[] = "INSERT INTO `".$Table2."` VALUES ".implode(",", $ValuesArray).";";
			 		unset($AllTables1[$CorrespKey]);
			 	}
				else
				{
					$FArray3[] = $Value2;
				}
			}
			else
			{
				$FArray3[] = $Value2;
			}
		}
		
		if ($this->Export == 'download')
		{
			$this->OpenFile();
			$this->WriteArrayToFile($FArray3);
		}
		else
		{
			foreach ($FArray3 as $item) { print $item."<br>\n"; }
		}
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
	

	function GetAllTables($FArray)
	{
		$AllTables = array();
		foreach($FArray as $Key=>$Value)
		{
			if (substr($Value, 0, 11) == "INSERT INTO")
			{
				$AllTables[$Key] = $this->GetTableName($Value);
			}	
		}
		return $AllTables;
	}	
	

	function GetTableName($QLine)
	{
		$pos = strpos($QLine, "` VALUES");
		if ($pos === FALSE)
		{
			$pos = strpos($QLine, "' VALUES");
			if ($pos === FALSE)
			{
				die("ERROR");
			}
		}
		return substr($QLine, 13, $pos-13);
	}	


	function GetNextTableStructure($QLine)
	{
		$TableArray = array(
			'Fields' => array(),
			'Primary' => array()
		);
		$PrimKeyPos = strpos($QLine, "PRIMARY KEY (");

		$ColsIni = strpos($QLine, "` (");
		$CleanValue = trim(substr($QLine, $ColsIni+3, $PrimKeyPos));
		$ColumnsArray = explode("`", $CleanValue);
		$IsOdd = FALSE;
		foreach ($ColumnsArray as $item)
		{
			if ($IsOdd)
			{
				$TableArray['Fields'][] = $item;
			}
			$IsOdd = !$IsOdd;
		}

		$PrimKeyEnd = strpos($QLine, ")", $PrimKeyPos);
		$CleanValue = substr($QLine, $PrimKeyPos+13, $PrimKeyEnd-$PrimKeyPos-13);
		$CleanValue = trim(str_replace("`", "", $CleanValue));
		$PrimaryArray = explode(",", $CleanValue);
		foreach ($PrimaryArray as $item)
		{
			$PrimKey = array_search($item, $TableArray['Fields']);
			if ($PrimKey !== FALSE)
			{
				$TableArray['Primary'][$PrimKey] = $item;
			}
		}
		
		return $TableArray;
	}


	function GetSeparateInserts($QLine)
	{
		$item = substr(trim($QLine), 0, -1);
		$item = str_replace(" (", "#(", $item);
		$item = str_replace(",(", "#(", $item);
		return explode("#", $item);
	}
	
	
	function GetRowIdentifier($Table, $TableArray, $Line, $Type)
	{
		$ReturnString = "";
		$ValParts = explode(",",trim($Line));
		foreach ($TableArray['Primary'] as $Key=>$Value)
		{
			if ($Type == "update")
			{
				if ($Key == 0)
				{
					$ValParts[0] = substr($ValParts[0], 1, strlen($ValParts[0])-1);
				}
				$ReturnString .= "`".$Table."`.`".$Value."` = ".$ValParts[$Key]." AND "; 
			}
			else
			{
				$ReturnString .= $ValParts[$Key].","; 
			}
		}
		if ($Type == "update")
		{
			return substr($ReturnString, 0, -5); 
		}
		else
		{
			return substr($ReturnString, 0, -1); 
		}
	}

	
	function GetRowChangesSQL($TableArray, $ValuesFrom1, $ValuesFrom2)
	{
		$CleanValue = substr(trim($ValuesFrom1), 0, -1);
		$ValuesArray1 = explode(",",$CleanValue);
		$CleanValue = substr(trim($ValuesFrom2), 0, -1);
		$ValuesArray2 = explode(",",$CleanValue);
		
		$ChangesArray = array();
		foreach ($ValuesArray2 as $Key=>$Value)
		{
			if ($Value != $ValuesArray1[$Key])
			{
				$ChangesArray[$Key] = $Value;
			}
		} 
		
		$ChangesSQL = "";
		foreach ($ChangesArray as $Key=>$Value)
		{
			$ChangesSQL .= " `".$TableArray['Fields'][$Key]."` = ".$Value.",";
		}
		return substr($ChangesSQL, 0, -1);
	}

}