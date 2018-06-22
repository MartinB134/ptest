<?php
/**
 * Frmwrk - the lightweight framework for the Netresearch programming test
 */

namespace Frmwrk;

/**
 * A simple yet flexible form class meant to cover validation, data retrieval
 * and support for form rendering
 * (via view scripts named by the elements type - use partials in the view to
 * render the form from outside)
 *
 * @package    Frmwrk
 * @subpackage Form
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 */
class Form extends \ArrayObject
{    
    /**
     * Form value / element name prefix - nest by dots:
     * outer.inner.prefix -> outer[inner][prefix] 
     * @var string
     */
    protected $prefix = '';
    
    /**
     * @var Form 
     */
    protected $parentForm;
    
    /**
     * Current validation result (present after {@link Form::setValues()}
     * @var boolean|null
     */
    protected $validationResult = null;

    /**
     * Form method
     * @var string
     */
    protected $method = 'post';
    
    /**
     * Form action
     * @var string
     */
    protected $action = '';

    /**
     * Constructor - construct your own form by overriding init()
     */
    final public function __construct() {
        parent::__construct(array());
        $this->init();
    }
    
    /**
     * Called right on __construction
     */
    public function init()
    {
    }

    /**
     * Set values / element names prefix - nest by dots:
     * outer.inner.prefix -> outer[inner][prefix] 
     * 
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
    
    /**
     * Get values / element names prefix
     * 
     * @return string
     */
    public function getPrefix()
    {
        if ($this->parentForm && $this->parentForm->prefix) {
            return $this->parentForm->prefix.'.'.$this->prefix;
        }
        return $this->prefix;
    }

    /**
     * Set form method
     * 
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * Get form method
     * 
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set form action
     * 
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * Get form action
     * 
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set the parent form (after added to it as sub form)
     * 
     * @param Form $form
     * @return Form
     */
    protected function setParentForm(Form $form)
    {
        $this->parentForm = $form;
        return $this;
    }

    /**
     * Get parent form
     * 
     * @return \Frmwrk\Form
     */
    public function getParentForm()
    {
        return $this->parentForm;
    }
    
    /**
     * Is this form a sub form?
     * 
     * @return boolean
     */
    public function isSubForm()
    {
        return !!$this->parentForm;
    }

    /**
     * Add an element to the form
     * 
     * @param string       $name           The element name (also attribute)
     * @param string|Form  $type           The type of the element - used to
     *                                     identify the responsible view script
     *                                     for rendering OR a subform instance
     * @param array|string $optionsOrLabel As the name suggests - options will
     *                                     be used by the view scripts later on
     *                                     (when the "required" option is true,
     *                                     a "notEmpty"-validation will automati
     *                                     cally be added 
     * @param array|null   $validations    Optional validations array passed to
     *                                     {@link Validator}
     * @throws Exception   When there's already an element with that name
     * @return \Frmwrk\Form
     */
    public function addElement($name, $type, $optionsOrLabel = null, $validations = null)
    {
        if ($this->offsetExists($name)) {
            throw new Exception('Form already has element with name '.$name);
        }
        
        if ($optionsOrLabel && !is_array($optionsOrLabel)) {
            $options = array('label' => $optionsOrLabel);
        } else {
            $options = (array) $optionsOrLabel;
        }
        
        if ($type instanceof self) {
            $options['form'] = $type;
            $type = 'form';
        }
        
        $options['type'] = $type;
        $options['validator'] = new Validator($validations);
        
        $this->offsetSet($name, $options);
        return $this;
    }
    
    /**
     * Add/Set an element or subform with the specified name. Options are
     * * type:      Required - type of the element (equals view script name)
     * * form:      Subform - required when type is "form"
     * * required:  If element is required (notEmpty-validation will be added)
     *   (optional)
     * * validator: Either an validator instance or an array of validations as
     *   (optional) you would pass it to {@see Validator::__construct()}
     * 
     * @param string     $name    The name of the element
     * @param array|Form $options Element options or the form
     */
    public function offsetSet($name, $options)
    {
        if ($options instanceof self) {
            $options = array(
                'type' => 'form',
                'form' => $options
            );
        } elseif (!is_array($options)) {
            throw new Exception('Options must be array or '.__CLASS__);
        }
        
        if (!array_key_exists('type', $options)) {
            throw new Exception('No element type given');
        }
        
        if ($options['type'] == 'form') {
            if (!array_key_exists('form', $options)) {
                throw new Exception('Subform must be set in key "form"');
            }
            if (!$options['form'] instanceof self) {
                throw new Exception('Subform must be instanceof '.__CLASS__);
            }
            $options['form']->setParentForm($this)->setPrefix($name);
        } else {
            $options['form'] = $this;
        }
        
        if (!array_key_exists('validator', $options)) {
            $options['validator'] = null;
        }
        if (!$options['validator'] instanceof Validator) {
            $options['validator'] = new Validator($options['validator']);
        }
        
        if (array_key_exists('required', $options) && $options['required']) {
            $options['validator']->prepend(array('notEmpty', null, true));
        }
        
        $options['name'] = $name;
        
        return parent::offsetSet($name, $options);
    }

    /**
     * Get an element by its name
     * 
     * @param string $name
     * @return array
     */
    public function getElement($name)
    {
        return $this->offsetGet($name);
    }
    
    /* (non-PHPdoc)
     * @see ArrayObject::offsetGet()
     */
    public function offsetGet($name)
    {
        $this->checkHasElement($name);
        return parent::offsetGet($name);
    }
    
    /**
     * Remove element
     * 
     * @param unknown_type $name
     * @return \Frmwrk\Form
     */
    public function removeElement($name)
    {
        $this->offsetUnset($name);
        return $this;
    }
    
    /* (non-PHPdoc)
     * @see ArrayObject::offsetUnset()
     */
    public function offsetUnset($name)
    {
        $this->checkHasElement($name);
        return parent::offsetUnset($name);
    }
    
    /**
     * Does this element exist in the form?
     * 
     * @param string $name
     * @return boolean
     */
    public function hasElement($name)
    {
        return $this->offsetExists($name);
    }
    
    /* (non-PHPdoc)
     * @see ArrayObject::append()
     */
    public function append($value)
    {
        if (!is_array($value) || !array_key_exists('name', $value)) {
            throw new Exception('name must be set');
        }
        $this->offsetUnset($value['name'], $value);
    }
    
    /**
     * Internal check that element exists
     * 
     * @param string $name
     * @throws Exception
     * @return boolean
     */
    protected function checkHasElement($name)
    {
        if (!$this->offsetExists($name)) {
            throw new Exception('No element with name '.$name);
        }
        return true;
    }

    /**
     * Set default values 
     * 
     * @param \Traversable $values
     * @return \Frmwrk\Form
     */
    public function setDefaults(\Traversable $values)
    {
        foreach ($values as $name => $value) {
            if (!$this->hasElement($name)) {
                continue;
            }
            $element = $this->getElement($name);
            if ($element['type'] == 'form') {
                $element['form']->setDefaults($value);
            } else {
                $element['value'] = $value;
                parent::offsetSet($name, $element);
            }
        }
        return $this;
    }
    
    /**
     * Set the values (from request - not the defaults!) - triggers validation
     * 
     * @param array $values
     * @return \Frmwrk\Form
     */
    public function setValues(array $values)
    {
        $this->validationResult = true;
        foreach ($values as $name => $value) {
            if (!$this->hasElement($name)) {
                continue;
            }
            $valid = true;
            $element = $this->getElement($name);
            if ($element['type'] == 'form') {
                $element['form']->setValues($value);
                if (!$element['form']->validationResult) {
                    $valid = false;
                }
            } else {
                if (!$element['validator']->validate($value)) {
                    $valid = false;
                } else {
                    $element['value'] = $value;
                }
            }
            $element['isValid'] = $valid;
            if (!$valid) {
                $this->validationResult = false;
            }
            parent::offsetSet($name, $element);
        }
        
        // Check for missing elements:
        foreach ($this as $name => $element) {
            if (array_key_exists($name, $values)) {
                continue;
            }
            if ($element['type'] == 'form') {
                $element['form']->setValues(array());
            } else {
                $element['isValid'] = $this[$name]['validator']->validate(null);
                parent::offsetSet($name, $element);
                if (!$element['isValid']) {
                    $this->validationResult = false;
                }
            }
        }
        
        return $this;
    }
    
    /**
     * Get the values (including eventually set defaults, excluding values that
     * failed validation)
     * 
     * @return array
     */
    public function getValues()
    {
        $values = array();
        foreach ($this as $name => $element) {
            if ($element['type'] == 'form') {
                $values[$name] = $element['form']->getValues();
            } else {
                $values[$name] = array_key_exists('value', $element) ? $element['value'] : null;
            }
        }
        return $values;
    }


    /**
     * Returns if all previously set values were valid
     * 
     * @throws Exception
     * @return boolean
     */
    public function isValid()
    {
        if ($this->validationResult === null) {
            throw new Exception('Not validated yet - setValues before');
        }
        return $this->validationResult;
    }

    /**
     * Get the view script that is responsible for rendering this element type
     * 
     * @param string $type
     * @return string
     */
    public function getViewScript($type = 'form')
    {
        return 'forms/elements/'.$type.'.phtml';
    }


    /**
     * Get the prefixed name attribute
     * 
     * @param string $name
     * @return string
     */
    public function prefix($name)
    {
        $prefix = $this->getPrefix();
        if ($prefix) {
            $parts = explode('.', $prefix);
            $parts[] = $name;
            $prefixed = array_shift($parts);
            foreach ($parts as $part) {
                $prefixed .= '['.$part.']';
            }
            return $prefixed;
        } 
        return $name;
    }
    
    /**
     * Render form into a view 
     * 
     * @param View $view
     * @param array $model
     * @return string
     */
    public function render(View $view, array $model = array())
    {
        $model['form'] = $this;
        return $view->partial($this->getViewScript(), $model);
    }
}