<?php
defined('APPLICATION_ROOT') or die();
require_once(APPLICATION_ROOT . DS . 'plugins'. DS . 'phorms' . DS . 'phorms.php');


class AuForm extends Phorm
{
    public $model = null;

    public function __construct($model, $data=array(), $multi_part=false, $method=Phorm::POST) {
        $this->model = $model;
        $model_data = $this->model_fields($model::$pkeys);
        $data = array_merge($model_data, $data);
        parent::__construct($method, $multi_part, $data);
    }

    protected function model_fields($pkeys) {
        $data = $this->model->data();
        foreach ($data as $field => $value) {
            if ( in_array($field, $pkeys) ) {
                $this->$field = new HiddenField(array('required'));
            }
            else {
                $this->$field = new TextField("First name", 25, 255, array('required'));
            }
        }
        return $data;
    }

    protected function define_fields() {
    }

    public function is_valid($reprocess=false) {
        if ( $reprocess || is_null($this->valid) )
        {
            if ( $this->is_bound() )
            {
                foreach($this->fields as $name => &$field)
                    if ( !$field->is_valid($reprocess) )
                        $this->errors[$name] = $field->get_errors();
                $valids = get_class_methods($this);
                foreach($valids as $valid)
                    if ( substr($valid, 0, 6) == 'valid_' )
                        $this->$valid();
                $this->valid = ( count($this->errors) === 0 );
            }
            if ( $this->valid && $this->is_bound() ) $this->clean_data();
        }
        return $this->valid;
    }
}


function required($value)
{
    if ($value == '' || is_null($value))
        throw new ValidationError('This field is required.');
}
