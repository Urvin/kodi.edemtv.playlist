<?php
//--------------------------------------------------------------------------------------------------------------------//
//
// CONVERT edem.tv playlist into Kodi IPTV Simple one
//
//--------------------------------------------------------------------------------------------------------------------//


// Имя входного файла плейлиста, скачанного с edem.tv
$lInputFileName = 'edem_pl.m3u8';

// Имя результирующего файла плейлиста, для установки в Kodi
$lOutputFileName = '../compiled_edem_pl.m3u';

// Файл телепрограммы, например с http://georgemikl.ucoz.ru/load/0-0-0-4-20
$lXmlTvFilename = 'xmltv.xml';

// Папка с пиктограммами
$lPiconsPath = '../picons/';
//$lPiconsPath = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'picons' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;


// Замена названий телеканалов
$lKnownReplacements = array(
		'TB 1000 Русское кино'    => 'TV1000 Русское кино',
		'РБК-ТВ'                  => 'РБК',
		'Tiji TV'                 => 'Tiji',
		'Европа Плюс'             => 'Europa Plus TV',
		'МУЗ'                     => 'МУЗ-ТВ',
		'Сов. Секретно'           => 'Совершенно секретно',
		'Телеканал «Россия»'      => 'Россия 1',
		'Россия-Культура'         => 'Россия К',
		'Россия-24'               => 'Россия 24',
		'ТВ3'                     => 'ТВ-3',
		'Disney'                  => 'Disney Channel',
		'KHL'                     => 'КХЛ ТВ',
		'Москва 24'               => 'Москва-24',
		'Эгоист'                  => 'Эгоист ТВ',
		'Playboy'                 => 'Playboy TV',
		'Телеканал Да Винчи'      => 'Da Vinci',
		'НТВ Мир'                 => 'НТВ-Мир',
		'Матч'                    => 'Матч ТВ',
		'nat geographic'          => 'National Geographic',
		'Jim Jam'                 => 'JimJam',
		'MTV'                     => 'MTV Россия',
		'РТР Планета'             => 'РТР-Планета',
		'Fox Russia'              => 'FOX',
		'Музыка'                  => 'Музыка Первого',
		'Paramount Comedy Russia' => 'Paramount Comedy',
);

// Исключаемые из результата телеканалы
$lExcludeChannels = array(
		'ATV',
		'Ararat',
		'MTV.AM',
		'MTV.AM HD',
		'PanArmenian',
		'Ար',
		'Արմենիա Tv',
		'Արմենիա Tv HD',
		'Արմնյուզ',
		'Դար 21',
		'Երկիր մեդիա',
		'Կենտրոն',
		'Հ1',
		'Հ1 International',
		'Հ2',
		'Հ3',
		'Շանթ',
		'Շողակաթ',
);

//--------------------------------------------------------------------------------------------------------------------//
// Do not modify
//--------------------------------------------------------------------------------------------------------------------//

if(!function_exists('mb_ucfirst'))
{
	function mb_ucfirst($string, $enc = 'UTF-8')
	{
		return mb_strtoupper(mb_substr($string, 0, 1, $enc), $enc) .
		mb_substr($string, 1, mb_strlen($string, $enc), $enc);
	}
}

if(!function_exists('mb_strcmp'))
{
	function mb_strcmp($a, $b)
	{
		return strcmp(
				preg_replace('#[^\w\s]+#', '', iconv('utf-8', 'ascii//TRANSLIT', $a)),
				preg_replace('#[^\w\s]+#', '',iconv('utf-8', 'ascii//TRANSLIT', $b))
		);
	}
}

if (!function_exists("mb_trim"))
{
	function mb_trim( $string )
	{
		$string = preg_replace( "/(^\s+)|(\s+$)/us", "", $string );

		return $string;
	}
}

function pushChannel(&$aChannelList, $aChannelInfo, &$AExcludeChannels)
{
	if(!empty($aChannelInfo) && !in_array($aChannelInfo['name'], $AExcludeChannels))
		$aChannelList[] = $aChannelInfo;
}

function sortChannels($a, $b)
{
	if($a['group'] == $b['group'])
		return mb_strcmp($a['name'], $b['name']);
	return mb_strcmp($a['group'], $b['group']);
}

//--------------------------------------------------------------------------------------------------------------------//

try
{

	$lHandler = fopen($lInputFileName, 'r');
	if ($lHandler)
	{
		$lChannels = array();
		$lCurrentChannel = null;

		while(($lLine = fgets($lHandler)) !== false)
		{
			$lLine = mb_trim($lLine);
			if(empty($lLine) || $lLine == '#EXTM3U')
				continue;

			if(mb_substr($lLine, 0, 7) == '#EXTINF')
			{
				//save previous channel
				pushChannel($lChannels, $lCurrentChannel, $lExcludeChannels);
				$lCurrentChannel = array();

				//fill current channel
				$lCurrentChannel['name'] = mb_trim(mb_substr($lLine, 10));

				//change channel name
				if(in_array($lCurrentChannel['name'], array_keys($lKnownReplacements)))
					$lCurrentChannel['name'] = $lKnownReplacements[$lCurrentChannel['name']];
			}
			if(mb_substr($lLine, 0, 7) == '#EXTGRP')
			{
				$lCurrentChannel['group'] = mb_ucfirst(mb_trim(mb_substr($lLine, 8)));
			}
			if(mb_substr($lLine, 0, 4) == 'http')
			{
				$lCurrentChannel['link'] = $lLine;
			}
		}

		pushChannel($lChannels, $lCurrentChannel, $lExcludeChannels);
		fclose($lHandler);


		//check for channel names in tv program
		$lXmlChannels = array();
		$lXmlObj = simplexml_load_file($lXmlTvFilename);
		foreach($lXmlObj->channel as $lChannel)
			$lXmlChannels[] = mb_trim((string)$lChannel->{'display-name'});

		//check names
		foreach($lChannels as &$lChannel)
		{
			$lChannelFound = false;
			foreach($lXmlChannels as &$lXmlChannel)
			{
				if(mb_strtolower($lChannel['name']) == mb_strtolower($lXmlChannel))
				{
					$lChannelFound = true;
					$lChannel['name'] = $lXmlChannel;
				}
			}

			if(!$lChannelFound)
				echo "TV mismatch   \t\t", $lChannel['name'], PHP_EOL;

			if(!file_exists(mb_convert_encoding($lPiconsPath . $lChannel['name'] . '.png', 'Windows-1251', 'UTF-8')) && !file_exists(mb_convert_encoding($lPiconsPath . $lChannel['name'] . '.jpg', 'Windows-1251', 'UTF-8')))
				echo "LOGO mismatch \t\t", $lChannel['name'], PHP_EOL;

		}


		// sort channels by group and name
		usort($lChannels, 'sortChannels');


		if(!$lHandler = fopen($lOutputFileName, 'w+'))
			throw new Exception('Cannot create or open output file for writing');
		fwrite($lHandler, '#EXTM3U');
		fwrite($lHandler, PHP_EOL);

		foreach($lChannels as &$lChannel)
		{
			fwrite($lHandler, '#EXTINF:0 group-title="' . $lChannel['group'] . '",' . $lChannel['name']);
			fwrite($lHandler, PHP_EOL);
			fwrite($lHandler, $lChannel['link']);
			fwrite($lHandler, PHP_EOL);
		}
		fclose($lHandler);

	}
	else
	{
		throw new Exception('Could not read input file');
	}

}
catch(Exception $e)
{
	echo 'ERROR: ', $e->getMessage();
}
