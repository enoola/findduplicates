#!/bin/php
<?Php
/*
 * Made by John PIGERET, john.pigeret@gmail.com
 ****** Purpose of this script :
 * create a simple file with filefullpath_and_name | sha1
 * First version wrote :2014/04/22
 * Revision 1 wrote : 2014/04/22 : handle restarting where we were..
 * Revision 2 from command line putFileSHA1InCSVFile : pathToFetch CSVFileToPutInformationIn.csv <Allow_start_from_where_we_left>
 * Revision 3 2014/04/27 add the capability to have a reference CSV File (this will allow us to check if we fetch the file already, if so we won't have to redo the SHA1 which takes time
 * Revision 4 2014/04/31 added m2ts, ifo, vob, bup
 * Revision 5 2014/04/03 added capabilityToChange the regex :)..
*/
error_reporting(E_ALL | E_STRICT);

//need this for our special fr char (...)
setlocale(LC_ALL, 'fr_FR.utf8');
define ('NEW_LINE', "\r\n");

//openssl
$SH_OPENSSL_PATH = '/opt/bin';


if ( !(is_dir($SH_OPENSSL_PATH) && file_exists($SH_OPENSSL_PATH . '/openssl') && is_executable($SH_OPENSSL_PATH . '/openssl')) )
	$SH_OPENSSL_PATH = getenv('OPENSSL_PATH');
	
if ($SH_OPENSSL_PATH === FALSE)
	die(__FILE__ . ' : ERROR : environment OPENSSL_PATH no found.'. NEW_LINE);
elseif(!( is_dir($SH_OPENSSL_PATH) && file_exists($SH_OPENSSL_PATH . '/openssl') && is_executable($SH_OPENSSL_PATH . '/openssl') ) )
	die (__FILE__ . ' : ERROR : '.$SH_OPENSSL_PATH . '/openssl no found or not executable.' . NEW_LINE);

$szOPENSSLFullPath = $SH_OPENSSL_PATH . '/openssl';

$paramExpected = __FILE__ .  ' : --path-to-fetch=<pathToFetch> --destination-csv-file=<CSVFileToPutInformationIn.csv> [--reference-csv-file=CSVReferenceFile.csv] [--csv-separator=CSV_Separator:|_MySeparator_|] [--allow-start-from-where-we-left=(true|false)] [--help]' . NEW_LINE;
//now we want to know which folder to analyse
$moreInfo = "--path-to-fetch=<folder>: the folder to fetch
--destination-csv-file=<filename> : filename where will put information collected. (filepath<CSV_Separator>SHA1)
Optional --reference-csv-file=<filename>:  a CSV file (generated previously by this script), that makes our script takes SHA1 from it (when possible) rather than calculating it again
Optional --csv-separator=<char|string> : by default '|_MySeparator_|'.
Optional --allow-start-from-where-we-left=<'true'|'false'> : allow to take other rather than starting from the beginning.
Optional --extensions-to-match=<expression> : default : 'avi|mkv|mp4|mpg|mpeg|divx|m2ts|ifo|vob' .
Optional --help : display this help
";


$arExpectedArgsReference = array(
array('name'=>'--path-to-fetch','mandatory'=>1), 
array('name'=>'--destination-csv-file','mandatory'=>1), 
array('name'=>'--reference-csv-file','mandatory'=>0), 
array('name'=>'--csv-separator','mandatory'=>0), 
array('name'=>'--allow-start-from-where-we-left','mandatory'=>0),
array('name'=>'--help','mandatory'=>0),
array('name'=>'--extensions-to-match','mandatory'=>0) 
);
$arWithArgsFromCli = array();
$indexArg = 0;
$bFoundMandatoryArg = false;
if ($argc < 2)
{
	echo $paramExpected . NEW_LINE;
	die ($moreInfo); 
}

if (($argc < 3) || ($argc > 6) )
{
	echo __FILE__ . ' : not enough or too much parameters'. NEW_LINE;
	die ($paramExpected); 
}
//seek for mandatory args
//really ugly
foreach ($arExpectedArgsReference as $arInfosArgs)
{
	if ($arInfosArgs['mandatory'] == 1)
	{
		foreach ($argv as $k => $val)
		{
			if (strncmp($arInfosArgs['name'],$val,strlen($arInfosArgs['name'])) == 0)
				$bFoundMandatoryArg = true;
		}
	}
	//anyway we'll put args in form into an array :)
	foreach ($argv as $k => $val)
	{
		if (strncmp($arInfosArgs['name'],$val,strlen($arInfosArgs['name'])) == 0)
		{
			$arSplittedCurArg = explode('=', $val);
			if ((count($arSplittedCurArg) < 2) or (strlen($arSplittedCurArg[1])==0))
			{
				echo __FILE__ . " : missing value for parameter : '" . $arInfosArgs['name'] . "'" . NEW_LINE;
				die ($paramExpected);
			}
			$arWithArgsFromCli[$arInfosArgs['name']] = $arSplittedCurArg[1];
			
		}
	}
	if (($arInfosArgs['mandatory'] == 1) && ($bFoundMandatoryArg == false))
	{
		echo __FILE__ . " : missing mandatory Arg : '" . $arInfosArgs['name'] . "'" . NEW_LINE;
		die ($paramExpected);
	}
	else
		$bFoundMandatoryArg = false;
}

if (array_key_exists('--help', $arWithArgsFromCli))
{
	echo $paramExpected . NEW_LINE;
	die ($moreInfo); 
}

if (!is_dir($arWithArgsFromCli['--path-to-fetch']))
{
	echo __FILE__ . " : ERROR : '".$arWithArgsFromCli['--path-to-fetch']."' isn't a folder or doesn't exist." . NEW_LINE;
	die ($paramExpected);
}
if ( array_key_exists('--allow-start-from-where-we-left', $arWithArgsFromCli) )
{
	if ( strncmp($arWithArgsFromCli['--allow-start-from-where-we-left'], 'true', 4) == 0 )
		define ('ALLOW_START_FROM_WHERE_WE_LEFT', TRUE);	
	else if ( strncmp($arWithArgsFromCli['--allow-start-from-where-we-left'], 'false',5) == 0 )
		define ('ALLOW_START_FROM_WHERE_WE_LEFT', FALSE);
	else
	{
		echo __FILE__ . " : ERROR : argument '--allow-start-from-where-we-left' must be either non-existent,false,true.your's is : '".$arWithArgsFromCli['--allow-start-from-where-we-left']."'". NEW_LINE;	
		die ($paramExpected); 	
	}
}
else
{
	define ('ALLOW_START_FROM_WHERE_WE_LEFT', FALSE);
}

if ((ALLOW_START_FROM_WHERE_WE_LEFT == FALSE) && file_exists($arWithArgsFromCli['--destination-csv-file']))
{
	echo __FILE__ . " : ERROR : output file '".$arWithArgsFromCli['--destination-csv-file']."' already exists and option '--allow-start-from-where-we-left' is set to false." . NEW_LINE;
	die ($paramExpected); 
}

$szSeparator = '|_MySeparator_|';
if ( array_key_exists('--csv-separator', $arWithArgsFromCli) )
	$szSeparator = $arWithArgsFromCli['--csv-separator'];

$cttReferenceCSVFile = array();
if (array_key_exists('--reference-csv-file', $arWithArgsFromCli))
{
	if (!file_exists($arWithArgsFromCli['--reference-csv-file']) or (! is_readable($arWithArgsFromCli['--reference-csv-file'])))
	{
		echo __FILE__ . " : ERROR : csv reference file '".$arWithArgsFromCli['--reference-csv-file']."' doesn't exists or isn't readable." . NEW_LINE;
		die ($paramExpected); 
	}
	else
		$cttReferenceCSVFile = getReferenceCSVFileContent($arWithArgsFromCli['--reference-csv-file'], $szSeparator);
}
$nCountReferenceCSVFileEntries = count($cttReferenceCSVFile);

$szExtensionToMatch = 'avi|mkv|mp4|mpg|mpeg|divx|m2ts|ifo|vob';
if ( array_key_exists('--extensions-to-match', $arWithArgsFromCli) )
	$szExtensionToMatch = $arWithArgsFromCli['--extensions-to-match'];


$FolderStart = $arWithArgsFromCli['--path-to-fetch'];
$outputFile = $arWithArgsFromCli['--destination-csv-file'];

//$patternToMatch = '/.(avi|mkv|mp4|mpg|mpeg|divx|m2ts|ifo|vob|bup|AVI|MKV|MP4|MPG|MPEG|DIVX|M2TS|IFO|VOB|BUP)$/i';
$patternToMatch = '/.('.$szExtensionToMatch.')$/i';
$oDirectoryIterator = new RecursiveDirectoryIterator($FolderStart);
$oRecursiveIterator = new RecursiveIteratorIterator($oDirectoryIterator, RecursiveIteratorIterator::SELF_FIRST);

$indexS = 0;
//if our CSV output file exists we might want to start from where we left
if (file_exists($outputFile))
{
	echo 'output CSV file : ' . $outputFile . ' already exists.' . NEW_LINE;
	if (ALLOW_START_FROM_WHERE_WE_LEFT===TRUE)
		echo 'ALLOW_START_FROM_WHERE_WE_LEFT = TRUE, so we will continue....' . NEW_LINE;
	else
		die ('ALLOW_START_FROM_WHERE_WE_LEFT = TRUE, so we will NOT continue.' . NEW_LINE);


	//if file doesn't seems in a good shape. 
	if (!verifyFileIntegrity($outputFile, $szSeparator))
		die ("file doesn't seem right look at the last line and remove it manually if it seems wise" . NEW_LINE);

	$szLastFilenameFectched = getLastFilenameFetched($outputFile,$szSeparator);

	//now ....
	//we will truncate our result...
	$index = 0;
	$bFilmFound = FALSE;
	foreach($oRecursiveIterator as $name => $object)
	{
		if(strcmp($name, $szLastFilenameFectched) == 0)
		{
			$indexS = $index;
			echo $szLastFilenameFectched.' found at index: ['.$index.'] . ' . NEW_LINE;
			$bFilmFound = TRUE;
			//break; -> bug in php 5.2 we need to loop to the end.
		}
		$index++;
	}

	if ($bFilmFound == FALSE)
		die ($szLastFilenameFectched . 'NOT Found !!! will stop ' . NEW_LINE);

}
else //($bFilmFound == TRUE)
{
	$szToWrite = 'filename' . $szSeparator . 'SHA1' . NEW_LINE;
	if (! writeInFile($outputFile, $szToWrite) )
	{
		die (__LINE__ . ' an error occured while trying to open/write (in) CSV File.' . NEW_LINE);
	}
}


//$index = 2000;
$indexi = 0;
foreach($oRecursiveIterator as $name => $object)
{
	if (($indexS > 0) && ($indexi <= $indexS))
	{
		$indexi++;
		continue;
	}
	else if ( preg_match($patternToMatch, $name) )
	{
		if ((! is_dir($name) ) && ( is_readable($name) ) )
		{
			$sha1OneFile = FALSE;
			if ($nCountReferenceCSVFileEntries > 0) //if there is a reference file basically and if it contains entry
				$sha1OneFile = getExistingFileSHA1($name, $cttReferenceCSVFile);
			if ($sha1OneFile === FALSE)
			{
				$sha1OneFile = my_sha1switch($name, $SH_OPENSSL_PATH);
			}	
			if ($sha1OneFile === FALSE)
				die('sha1_file returned false for : '.$name.NEW_LINE);

			$szToWrite = $name . $szSeparator . $sha1OneFile . NEW_LINE;					
			if ( ! writeInFile($outputFile, $szToWrite) )
				die (__LINE__ . ' an error occured while trying to open/write (in) CSV File.' . NEW_LINE);

		}
	}

}

function getExistingFileSHA1($szFilenameToSeekFor, $arContentCSVFile)
{
	foreach($arContentCSVFile as $arEntry)
	{
		if ( strcmp($szFilenameToSeekFor,$arEntry[0]) == 0)
			return ($arEntry[1]);
	}
	return ( FALSE );
}

function getReferenceCSVFileContent($szFilename,$szSeparator)
{
	$arContentCSVFile = file($szFilename, FILE_IGNORE_NEW_LINES);
	$arCttExploded = array();
	$countLine = count($arContentCSVFile);
	if ($countLine < 2)
	{
		return ( array() );
	}
	array_shift($arContentCSVFile);
	foreach ($arContentCSVFile as $line)
	{
		$arLineExploded = explode($szSeparator, $line);
		$arCttExploded[] = $arLineExploded;
	}
	
	return ($arCttExploded);
}
	
function getLastFilenameFetched($szFilename,$szSeparator)
{
	$arFileContent = file($szFilename, FILE_IGNORE_NEW_LINES);
	$countLine = count($arFileContent);
	if ($countLine < 2)
	{
		return ( FALSE );
	}	
	$szLastLine = $arFileContent[$countLine-1];
	$arFileContent = null;
	
	$arFormatted= explode($szSeparator, $szLastLine);
	
	//we just verify we do have a SHA1 and that it does contain 40 char..
	if ( count($arFormatted) == 2 )
		if (strlen($arFormatted[1]) == 40)
			return ( $arFormatted[0] );

	return ( FALSE );
}

//this function return TRUE if the last line looks well formatted.
function verifyFileIntegrity($szFilename, $szSeparator)
{
	$arFileContent = file($szFilename, FILE_IGNORE_NEW_LINES);
	$countLine = count($arFileContent);
	if ($countLine < 2)
	{
		return ( FALSE );
	}	
	$szLastLine = $arFileContent[$countLine-1];	
	$arFileContent = null;

	$arFormatted= explode($szSeparator, $szLastLine);
	
	//we just verify we do have a SHA1 and that it does contain 40 char..
	if ( count($arFormatted) == 2 )
		if (strlen($arFormatted[1]) == 40)
			return ( TRUE );

	return ( FALSE );
}

function writeInFile($outputFile, $szToWrite)
{
	$fd = fopen($outputFile,'a');
	if ($fd == FALSE )
		return ( FALSE );
		
	$lenToWrite = strlen($szToWrite);
	$ret = fwrite( $fd, $szToWrite, $lenToWrite);
	if ($fd === FALSE)
	{
		fclose($fd);
		return ( FALSE );
	}
	
	fclose($fd);
	return ( TRUE );
}

//use a php call when possible, else native system call
function my_sha1switch($file, $SH_OPENSSL_PATH)
{
	$fsize = @filesize($file);
	//if false we go for the native function
	if ($fsize === FALSE )
	{
		$sha1OneFile = execOpensslSHA1($file, $SH_OPENSSL_PATH);
	}
	else
	{
		$sha1OneFile = sha1_file($file);
	}
	return ( $sha1OneFile );
}

function execOpensslSHA1($file, $SH_OPENSSL_PATH = '/opt/bin')
{
	$OpensslSHA1 = $SH_OPENSSL_PATH . '/openssl';
	$arRet = pipe_execReadingStdOut($OpensslSHA1 .' sha1 '. escapeshellarg($file));
	
	//example of return : SHA1(/volume4/Videos/Films/Historique/Max_2003_FR.avi)= feec60b5318a524c33742f8faed44ae02d42fa68
	//we must remove the last line which is empty
	$outputAsArray = explode("\n", $arRet[1]);
	array_pop($outputAsArray);
	
	$splitEntry = explode('= ', $outputAsArray[0]);
	$sha1OfTheFile = $splitEntry[1];

	return($sha1OfTheFile);
}

function pipe_execReadingStdOut($cmd, $input='') 
{
	$pipes = null;
    $proc = proc_open($cmd, array(0=>array('pipe', 'r'),// stdin is a pipe that the child will read from
                                  1=>array('pipe', 'w'),// stdout is a pipe that the child will write to
                                  2=>array('pipe', 'w')), $pipes);// stderr in viriable
  
	if (strlen($input) > 0)
	{
		fwrite($pipes[0], $input, strlen($input));
    }
	fclose($pipes[0]);
	
	$stdout = stream_get_contents($pipes[1]);
	fclose($pipes[1]);

	$stderr = '';
	$lenToReadAtATimeFromStdErr = 512;
	$stderrpart = stream_get_contents($pipes[2], $lenToReadAtATimeFromStdErr);
    while ( (!feof($pipes[2])) && (strlen($stderrpart) > 0) && (strlen($stderrpart) <= $lenToReadAtATimeFromStdErr) )
	{
		$stderr .= $stderrpart;
		$stderrpart = stream_get_contents($pipes[2], $lenToReadAtATimeFromStdErr);
	}
	$stderr .= $stderrpart;

    $return_code = (int)proc_close($proc);

    return array($return_code, $stdout, $stderr);
}


exit(0);

?>