<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Feed JOB
 */
class Feed extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config->load('extensions/FHC-Core-DVUH/DVUHClient');
		$this->load->library('extensions/FHC-Core-DVUH/FeedReaderLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Get Feed Messages
	 */
	public function get()
	{
		$this->load->model('extensions/FHC-Core-DVUH/Feed_model', 'FeedModel');

		$this->logInfo('Feed GET job start');


		$be = $this->config->item('fhc_dvuh_be_code');
		$content = 'true';
		$erstelltSeit='2020-05-01';
		$markread='false';

		$queryResult = $this->FeedModel->get($be, $content, $erstelltSeit, $markread);

		//var_dump($queryResult);

		/*if (isError($queryResult))*/

		if (hasData($queryResult))
		{
			$this->feedreaderlib->getFeeds(getData($queryResult));

			//var_dump(getData($queryResult));
/*			$obj = simplexml_load_string(getData($queryResult));
			$title = (string) $obj->title;
			$entries = $obj->entry;*/

			//var_dump($elements[0]);
/*
			if (!($x = simplexml_load_string(getData($queryResult))))
				return;*/

			//var_dump($x);

/*			foreach ($x->channel->item as $item)
			{
				$post = new stdClass();
				$post->date  = (string) $item->pubDate;
				$post->ts    = strtotime($item->pubDate);
				$post->link  = (string) $item->link;
				$post->title = (string) $item->title;
				$post->text  = (string) $item->description;

				// Create summary as a shortened body and remove images,
				// extraneous line breaks, etc.
				//$post->summary = $this->summarizeText($post->text);
				var_dump($post->text);

				//$this->posts[] = $post;
			}*/

/*			$xml = new SimpleXMLElement(getData($queryResult));
			$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><dv:kontostandantwort xmlns:dv="http://www.brz.gv.at/datenverbund-unis" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.brz.gv.at/datenverbund-unis https://stubei-q.portal.at/rws/schema/datenverbund-jobs-0.5.xsd" xsi:noNamespaceSchemaLocation="https://stubei-q.portal.at/rws/schema/datenverbund-jobs-0.5.xsd">
        <dv:uuid>34be97ef-db3d-439b-95b5-da06c5b81c05</dv:uuid>
        <dv:fehlerliste fehleranzahl="0" />
        <dv:kontostandliste>
          <dv:kontostand>
            <dv:bezahlstatus>9</dv:bezahlstatus>
            <dv:kontostand>1920</dv:kontostand>
            <dv:buchungsdatum>2020-05-13</dv:buchungsdatum>
            <dv:buchungsbe>FT</dv:buchungsbe>
            <dv:studierendenkey>
              <dv:matrikelnummer>51902435</dv:matrikelnummer>
              <dv:be>FT</dv:be>
              <dv:semester>2020S</dv:semester>
            </dv:studierendenkey>
          </dv:kontostand>
        </dv:kontostandliste>
      </dv:kontostandantwort>
');

			$xpath = $xml->xpath('//dv:kontostandantwort/dv:kontostandliste/dv:kontostand/*');


			foreach ($xpath as $child)
			{
				var_dump($child->getName());
				var_dump($child);
			}*/

/*			foreach($xml->xpath('//dv:kontostandantwort') as $kontostand) {
				var_dump($kontostand->xpath('kontostandantwort:kontostand'));
			}*/


		}
		else
		{
			$this->logInfo('No elements were found');
		}

		$this->logInfo('Feed GET job stop');
	}


}
