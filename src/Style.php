<?php

declare(strict_types=1);

namespace BladeUI\Icons;

use BladeUI\Icons\Concerns\RendersAttributes;
use Illuminate\Contracts\Support\Htmlable;

class Style extends SvgElement
{
    use RendersAttributes;

    private array $classes;

    public function __construct(string $svgContent, string $name)
    {
        $styleContent = parent::findGroupElement($svgContent, 'style');
        if($styleContent === false || !isset($styleContent[0])){
            $styleContent = new SvgElement('style', '', []);
        }else{
            $styleContent = $styleContent[0];
        }
        parent::__construct('style', $styleContent->contents(), $styleContent->attributes());
        $this->renameStyle($name);
        $this->removeContents();
    }

    public function renameStyle(string $svgElementName)
    {
        $this->classes = [];
        preg_match_all("/.([a-z0-9]*)({[^}]*})/i", $this->contents(), $comands);
        foreach($comands[1] as $k => $class){
            $className = $class . '-' . $svgElementName; 
            $this->classes[$className] = $comands[2][$k];
            // $this->setContents(str_replace($class, $className, $this->contents()));
        }
        return $this;
    }

    public function classes() : array
    {
        return $this->classes;
    }

    public function setClasses($classes) : self
    {
        $this->classes = $classes;
        return $this;
    }

    public function toHtml(): string
    {
        $ret = '<style' . sprintf('%s', $this->renderAttributes()) . ' >' . "\n";
        $classes = $this->classes();
        foreach($classes as $className => $comands){
            $ret .= '.' . $className . ' ' . $comands . "\n";
        }
        $ret .= '</style>';
        return $ret;
    }
    public function toCss(): string
    {
        $ret  = "/* ## Automatically generated code ## \n";
        $ret .= "   ##             ASK              ## \n";
        $ret .= "   ################################## */\n";
        $classes = $this->classes();
        foreach($classes as $className => $comands){
            $ret .= '.' . $className . ' ' . $comands . "\n";
        }
        return $ret;
    }

    public function mergeStyles(Style $add): self
    {
        if(empty($this->attributes)){
            $this->attributes = [];
        }
        if(empty($add->attributes)){
            $add->attributes = [];
        }
        // $cont = $this->contents() . $add->contents();
        $this->attributes = array_merge($this->attributes(), $add->attributes());
        $this->classes = array_merge($this->classes(), $add->classes());
        // $this->setContents($cont);
        return $this;
    }

    public function toArray()
    {
        $ret = parent::toArray();
        $ret['classes'] = $this->classes();
        return $ret;
    }
}
