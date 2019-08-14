<?php
class Intuiko_ConnectedStore_Block_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{

	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
		$this->setElement($element);

		$url = Mage::getBaseUrl() . "ping/ping";
		
		$jsScript = "document.getElementById('intuiko_connectedstore_test_connection').innerHTML = 'Trying to connect to the ICS API...';";
		$jsScript .= "var xmlhttp = new XMLHttpRequest();";
		$jsScript .= "xmlhttp.onreadystatechange = function() {";
		$jsScript .= "if (xmlhttp.readyState == XMLHttpRequest.DONE ) {";
		$jsScript .= "if(xmlhttp.status == 200){";
		$jsScript .= "document.getElementById('intuiko_connectedstore_test_connection').innerHTML = xmlhttp.responseText;";
		$jsScript .= "}else{";
		$jsScript .= "document.getElementById('intuiko_connectedstore_test_connection').innerHTML = 'Connection failed';";
		$jsScript .= "}}};";
		$jsScript .= "xmlhttp.open('GET', '" . $url . "', true);";
		$jsScript .= "xmlhttp.send();";
		
		
		
		$html = $this->getLayout()->createBlock('adminhtml/widget_button')
		->setType('button')
		->setClass('scalable')
		->setLabel('Test Connection')
		->setOnClick($jsScript)
		->toHtml();
		
		$html .= "<div id='intuiko_connectedstore_test_connection'></div>";

		return $html;
	}
}
?>