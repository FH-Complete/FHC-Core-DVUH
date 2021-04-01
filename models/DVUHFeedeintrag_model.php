<?php


class DVUHFeedeintrag_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_dvuh_feedeintrag';
		$this->pk = 'feedeintrag_id';
	}

	public function saveFeedeintrag($feedeintrag)
	{
		$this->addSelect('feedeintrag_id');
		$checkIfSaved = $this->loadWhere(array('id' => $feedeintrag->id));

		if (isError($checkIfSaved))
			return $checkIfSaved;

		$feedeintragToSave = array(
			'title' => $feedeintrag->title,
			'author' => $feedeintrag->author,
			'id' => $feedeintrag->id,
			'published' => $feedeintrag->published,
			'content' => $feedeintrag->contentXml
		);

		if (hasData($checkIfSaved))
		{
			$feedeintrag_id = getData($checkIfSaved)[0]->feedeintrag_id;

			return $this->update($feedeintrag_id, $feedeintragToSave);
		}
		else
		{
			return $this->insert($feedeintragToSave);
		}
	}
}