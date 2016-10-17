<?php
class ManageAttributesMagento{
	private static $_instance = null;

	protected $_attributes=array();
	protected $_options = array();

	private $_new_attribute_log = 'new_attribute.csv';
	/**
	 *
	 * @return ManageAttributesMagento
	 */
	public static function getInstance(){
		if(is_null(self::$_instance)){
			self::$_instance = new ManageAttributesMagento();
		}
		return self::$_instance;
	}

	private function __construct(){

	}

	public function initAttributeByCode($code,$need_correct=false){
		if($need_correct){
			$code = $this->correctAttributeCodeFromName($code);
		}
		if(!isset($this->_attributes[$code])){
			$sql = "SELECT attribute_id FROM msto_eav_attribute WHERE attribute_code='{$code}' AND entity_type_id=4";
			$id = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne($sql);
			if($id){
				$this->_attributes[$code] = $id;
			}else{
				$this->_attributes[$code]=false;
			}
		}
		if($this->_attributes[$code]){
			$this->getAllOptions($code);
		}
	}

	public function correctAttributeCodeFromName($name){
		$label=trim(strtolower($name));
		$label = str_replace(array('.','(',')'),'',$label);
		$label = str_replace(array(' ','&'),'_',$label);
		//$label = str_replace('__','_',$label);
		$label = preg_replace('/__+/', '_', $label);
		if($label=='weight'){
			$label='weight_weight';
		}
		if($label=='type'){
			$label = 'usage_type';
		}
		return $label;
	}

	public function correctOptionsLabel($label){
		$label = preg_replace('/\s+/', ' ', trim($label));
		return $label;
	}
	protected function getAllOptions($code){
		//$attributeModel = Mage::getModel('eav/entity_attribute')->load($this->attribute_id);
		// 		$attributeModel = Mage::getModel('eav/config')->getAttribute('catalog_product',$this->_attributes[$code]);
		// 		$src =  $attributeModel->getSource()->getAllOptions();
		$id = $this->_attributes[$code];
		$sql = "SELECT DISTINCT ov.value as label, o.option_id FROM msto_eav_attribute_option as o INNER JOIN msto_eav_attribute_option_value AS ov ON ov.option_id=o.option_id AND ov.store_id=0 WHERE o.attribute_id={$id}";
		$result = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
		$this->_options[$code]=array();
		if(count($result)){
			foreach ($result as $o) {
				if(!empty($o['label'])){
					$label = mb_strtolower($o['label']);
					$label = $this->correctOptionsLabel($label);
					$this->_options[$code][$label]=$o['option_id'];
				}
			}
		}
		return $this;
	}
	public function getOptionValue($code,$label){
		if(!isset($this->_attributes[$code])){
			$this->initAttributeByCode($code,false);
		}
		if(!isset($this->_options[$code])) return false;
		$label = $this->correctOptionsLabel($label);
		$l = mb_strtolower($label);
		$val = 0;

		if(isset($this->_options[$code][$l])){
			return $this->_options[$code][$l];
		}
		return $this->__createAttributeOptions($code, $label,$l);
	}
	public function __createAttributeOptions($code,$label,$correction_label){
		// 		$attribute_model        = Mage::getModel('eav/entity_attribute');
		// 		$attribute              = $attribute_model->load($this->_attributes[$code]);

		// 		$value['option'] = array($label,$label);
		// 		$result = array('value' => $value);
		// 		$attribute->setData('option',$result);

		// 		$attribute->save();
		$id = $this->_attributes[$code];
		if($id){
			$sql = "SELECT count(*) FROM msto_eav_attribute_option WHERE attribute_id={$id}";
			$position = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne($sql);
				
			$write = Mage::getSingleton('core/resource')->getConnection('core_write');
			$count = $write->insert('msto_eav_attribute_option',array('attribute_id'=>$id,'sort_order'=>$position));
			if($count){
				$option_id = $write->lastInsertId();
				if($option_id){
					$write->insert('msto_eav_attribute_option_value',array('option_id'=>$option_id,'store_id'=>0,'value'=>$label));
					$this->_options[$code][$correction_label] = $option_id;
					return $option_id;
				}
			}
		}
		return false;
	}
}