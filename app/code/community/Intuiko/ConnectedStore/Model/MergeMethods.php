<?php

class Intuiko_ConnectedStore_Model_MergeMethods
{
	public function toOptionArray()
	{
		return array(
			array('value'=>'CLASSIC', 'label'=>'Classic'),
			array('value'=>'KEEP_HIGHEST_QUANTITIES', 'label'=>'Keep highest quantities'),
			array('value'=>'KEEP_SLAVE', 'label'=>'Keep slave'),
			array('value'=>'KEEP_MASTER', 'label'=>'Keep master'),
			array('value'=>'EXCLUSIVE_ADD_FROM_SLAVE', 'label'=>'Exclusive add from slave'),
			array('value'=>'EXCLUSIVE_ADD_FROM_MASTER', 'label'=>'Exclusive add from master')
		);
	}
}
