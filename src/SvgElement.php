<?php

declare(strict_types=1);

namespace BladeUI\Icons;

use BladeUI\Icons\Concerns\RendersAttributes;
use Exception;
use Illuminate\Contracts\Support\Htmlable;

class SvgElement implements Htmlable
{
    use RendersAttributes;

    public const SVG_ATTRIBUTES = [
        'view-box',
        'viewBox',
        'version',
        'width',
        'height'
    ];

    public const GRAPH_ELEMENTS = [
        'line',
        'rect',
        'circle',
        'ellipse',
        'path',
        'image',
        'use',
        'stop',
        'feMorphology',
        'feTurbulence',
        'feDisplacementMap',
        'feFlood',
        'feColorMatrix',
        'feOffset',
        'feGaussianBlur',
        'feComposite',
        'feBlend',
        'g',
        'style',
        'text',
        'clipPath',
        'title',
        'linearGradient',
        'defs',
        'filter',

    ];

    public const REGEXP = [
        'groupElement' => '/(<(element)(?:\s[^>]*\/?>|\/>|>))([.\s<a-z="-:;>]*?)(<\/\2>)?/i',
        'monGroupElement' => '/(<element(?:\s[^>]*\/?>|\/>|>))/i'
    ];

    public const NON_GROUP_ELEMENTS = [
        'line',
        'rect',
        'circle',
        'ellipse',
        'path',
        'image',
        'use',
        'stop',
        'feMorphology',
        'feTurbulence',
        'feDisplacementMap',
        'feFlood',
        'feColorMatrix',
        'feOffset',
        'feGaussianBlur',
        'feComposite',
        'feBlend',
    ];

    public const GROUP_ELEMENTS = [
        'g',
        'style',
        'text',
        'clipPath',
        'title',
        'defs',
        'filter',
        'linearGradient',
    ];

    private string $name;

    private string $contents;

    private array $conteinedElements = [];

    public function __construct(string $name, string $contents, array $attributes = [])
    {
        $this->name = $name;
        $this->contents = $contents;
        $svg = preg_match("/<svg[^>]*>/i", $contents, $svgTag);
        if ($svg !== 0 && $svg !== false) {
            $attributes = array_merge($attributes, $this->getElementAttributes($svgTag[0]));
        }
        foreach ($attributes as $key => $attribute) {
            $this->$key($attribute);
        }
        $this->contents = $contents = str_replace($svgTag, '', $contents);
        foreach (self::GRAPH_ELEMENTS as $element) {
            if (stripos($contents, '<' . $element) !== false) {
                $this->conteinedElements[] = $element;
            }
        }
        if (!in_array($name, self::NON_GROUP_ELEMENTS)) {
            $this->getAllElements();
        }
        if ($name !== 'style') {
            $this->removeContents();
        }
    }

    public function __get($name)
    {
        try {
            if (method_exists($this, $name)) {
                return $this->$name();
            }
            if (property_exists($this, $name)) {
                return $this->$name;
            }
            
        } catch (Exception $e) {
            return null;
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName($name): self
    {
        $this->name = $name;
        return $this;
    }

    public function contents(): string
    {
        if (isset($this->contents)) {
            return $this->contents;
        }
        $elements = get_object_vars($this);
        if ($elements === false) {
            return '';
        }
        $ret = '';
        foreach ($elements as $element) {
            if (is_array($element)) {
                foreach ($element as $svg) {
                    if ($svg instanceof SvgElement) {
                        $ret .= $svg->toHtml() . "\n";
                    }
                }
            }
        }

        return $ret;
    }

    public function removeContents(): self
    {
        unset($this->contents);
        return $this;
    }

    public function setContents($contents): self
    {
        $this->contents = $contents;
        return $this;
    }

    public function getAllElements(): void
    {
        $content =  str_replace("\n", '',$this->contents());
        $content =  str_replace("  ", '',$content);
        while($content !== ''){
            $firstElement = preg_match('/<([^\s\/]+)/i', $content, $element);
            if(!isset($element[1]) || $firstElement === 0){
                return;
            }
            $element = is_array($element[1])?$element[1][0]:$element[1];
            preg_match('/(<('.$element.')(?:\s[^>]*\/?>|\/>|>))([^\']+?)(<\/\2>)/i', $content, $group);
            preg_match('/(<('.$element.')(?:\s[^>]*\/?>|\/>|>))/i', $content, $nonGroup);
            if(empty($group)){
                $svgElement = $this->findNonGroupElement($content, $element);
                $elementArray = [$svgElement];
            
                if(is_array($this->$element)){
                    
                    $elementArray = [...$this->$element, $svgElement];
                }
                $this->$element = $elementArray;
                
                $content = str_replace($nonGroup[0], '',$content);
            }else{
                $svgElement = $this->findGroupElement($content, $element);
                $elementArray = [$svgElement[0]];
                if(is_array($this->$element)){
                    $elementArray = [...$this->$element, $svgElement[0]];
                }
                $this->$element = $elementArray;
                $content = str_replace($svgElement[1], '',$content);
            }
        }
    }

    public function isGrouped(string $element)
    {

        foreach (self::GROUP_ELEMENTS as $type) {
            if($this->isInside($type, $element)){
                return true;
            }
        }
        return false;
    }

    public function isInside(string  $groupElement, string $element, $offset = 0)
    {
        $elementFirstPos = stripos($this->contents(), '<' . $element, $offset);
        $first = stripos($this->contents(), '<' . $groupElement, $offset);
        if ($elementFirstPos <= $first || $first === false) {
            return false;
        }
        $close = stripos($this->contents(), '</' . $groupElement, $offset);
        if ($elementFirstPos < $close) {
            return true;
        }
        $tmpContent = mb_substr($this->contents(), $first, $close);
        $nElements = mb_substr_count($tmpContent, '<' . $groupElement);
        if ($nElements === 0) {
            return false;
        }
        for ($i = 0; $i < $nElements; $i++) {
            $close = stripos($this->contents(), '</' . $groupElement, $close);
            if ($elementFirstPos < $close) {
                return true;
            }
        }
        $this->isInside($groupElement, $element, $close+2);
    }
    
    public function removeComents(): self
    {
        $this->contents = preg_replace("/(<!--.+-->\n)/i", '', $this->contents());
        return $this;
    }

    public function removeXmlFromContent($contents): string
    {
        return preg_replace("/(<?xml.+>\n)/i", '', $contents);
    }

    public function removeStylefromContent(): self
    {
        $this->contents = preg_replace("/(<[^>]*>[^<]*<\/style>\n)/i", '', $this->contents());
        return $this;
    }

    public function toHtml(): string
    {
        if (in_array($this->name(), self::GROUP_ELEMENTS)) {
            return sprintf('<' . $this->name() . '%s', $this->renderAttributes()) . '>' . $this->contents() . '</' . $this->name() . '>';
        } elseif (in_array($this->name(), self::NON_GROUP_ELEMENTS)) {
            return sprintf('<' . $this->name() . '%s', $this->renderAttributes()) . '/>';
        } else {
            return '<svg' . sprintf('%s', $this->renderAttributes()) . ' >' . "\n" . $this->contents() . "\n" . '</svg>';
            // return str_replace('<svg', sprintf('<svg%s', $this->renderAttributes()), $this->contents());
        }
    }

    public function removeId(): self
    {
        if (property_exists($this, 'id')) {
            unset($this->id);
        }
        return $this;
    }

    public function toArray()
    {
        $elem = get_object_vars($this);
        $ret = [];
        foreach ($elem as $key => $element) {
            if ($key === 'contents') {
                continue;
            }
            if (is_a($element, 'BladeUI\Icons\SvgElement')) {
                $ret[$key] =  $element->toArray();
                continue;
            }
            if ($key === 'attributes') {
                $ret['attributes'] =  $this->attributes();
                foreach ($ret['attributes'] as $k => $att) {
                    $ret['attributes'][$k] = str_replace(['\"', '"'], '', $att);
                }
                continue;
            }
            if (is_array($element)) {
                foreach ($element as $k => $elm) {
                    if (is_a($elm, 'BladeUI\Icons\SvgElement')) {
                        $ret[$key . '-' . $k] = $elm->toArray();
                    } else {
                        $ret[$key . '-' . $k] = $elm;
                    }
                }
                continue;
            }
            $ret[$key] =  $element;
        }
        return $ret;
    }

    public function serialize($data)
    {
        return json_encode($this->toArray());
    }

    public function unserialize($data)
    {
    }

    public function findGroupElement(string $content, string $element)
    {
        $count = mb_substr_count($content, '</' . $element);
        if ($count <= 0) {
            return false;
        }

        if ($count === 1) {
            $posStart = stripos($content, '<' . $element);
            $posEnde = stripos($content, '</' . $element . '>');
            $tag = trim(substr($content, $posStart,  stripos($content, '>', $posStart) - $posStart + 1));
            $cont = trim(substr($content, $posStart + strlen($tag), $posEnde - $posStart - strlen($tag)));
            $toRemove = trim(substr($content, $posStart , $posEnde - $posStart + strlen('</' . $element . '>')));
            $attributes = $this->getElementAttributes($tag);
            $tmp = new SvgElement($element, $cont, $attributes);
            return [$tmp, $toRemove];
        }

        $posStart[0] = stripos($content, '<' . $element);
        $posEnde[0] = stripos($content, '</' . $element);
        
        for ($i = 1; $i < $count; $i++) {
            $posStart[$i] = stripos($content, '<' . $element, $posStart[$i - 1] + 2);
            $posEnde[$i] = stripos($content, '</' . $element, $posEnde[$i - 1] + 2);
        }
        for ($i = 0; $i < $count; $i++) {
            $pos[$posStart[$i]] = 1;
            $pos[$posEnde[$i]] = -1;
        }
        ksort($pos);
        $n = 0;
        $first = array_key_first($pos);
        
        foreach ($pos as $key => $val) {
            if ($first === true) {
                $first = $key;
            }
            $n += $val;
            if ($n === 0) {
                preg_match('/(<('.$element.')(?:\s[^>]*\/?>|\/>|>))/i', $content, $tag, 0, $first);
                $cont = trim(substr($content, $first + strlen($tag[0]), $key - $first - strlen($tag[0])));
                $toRemove = trim(substr($content, $first , $key - $first + strlen('</' . $element . '>')));
                $attributes = $this->getElementAttributes($tag[0]);
                $tmp = new SvgElement($element, $cont, $attributes);
                return [$tmp, $toRemove];
            }
        }
        return false;
    }

    public function findNonGroupElement(string $content, string $element)
    {
        preg_match_all("/(<(" . $element . ")(?:\s[^>]*\/?>|\/>|>))([.\s<a-z=\"-:;>]*?)(<\/\2>)?/i", $content, $match);
        $tags = $match === [] ? '' : $match[0];
        $content = '';
        if ($tags === false || $tags === []) {
            return false;
        }
        foreach ($match as $tag) {
            $attributes = $this->getElementAttributes($tag[0]);
            $tmp = new SvgElement($element, $content, $attributes);
            return $tmp;
        }
        return false;
    }

    public function getElementAttributes(string $tag): array
    {
        preg_match_all("/[a-z0-9_$:-]*=[\"'][a-zA-Z0-9.,:;_?=%$+*#\(\)\/\n\t\r\s\\-]*[\"']/i", $tag, $attrs);
        $attributes = [];
        foreach ($attrs[0] as $attribute) {
            $attributes[explode('=', $attribute)[0]] = str_replace('"', '', explode('=', $attribute)[1]);
        }
        return  $attributes;
    }

    public function cleanGroup(): self
    {
        foreach ($this as $component => $svgElement) {
            if ($component === 'g') {
                foreach ($this->$component as $k => $g) {
                    if (property_exists($g, 'g') && count($g->g) === 1) {
                        $gOld = $g->g[0];
                        $gAttribute = $g->attributes();
                        $this->$component[$k] = $gOld;
                        $gAttribute = array_merge($this->$component[$k]->attributes(), $gAttribute);
                        foreach ($gAttribute as $attName => $val) {
                            $this->$component[$k]->$attName($val);
                        }
                    }
                    $g->clean();
                }
            }
        }
        return $this;
    }

    public function removeSvgAttribute()
    {
        foreach (self::SVG_ATTRIBUTES as $att) {
            if (isset($this->attributes()[$att])) {
                $this->remove($att);
            }
        }
        foreach ($this->attributes() as $att => $val) {
            if (preg_match('/xml|xmsln[:]?(svg?)/', $att, $match) !== 0) {
                $this->remove($att);
            }
            if (preg_match('/sodipodi[:]?[^=]*?/', $att, $match) !== 0) {
                $this->remove($att);
            }
            
        }
    }

    public function removeNonSvgAttr()
    {
        foreach ($this->attributes() as $name => $att) {
            if (!in_array($name,self::SVG_ATTRIBUTES)
            && preg_match_all('/(xml|xmsln)[:]?.*/i', $name, $match) === 0
            && preg_match_all('/(sodipodi)[:]?.*/i', $name, $match) === 0) {
                $this->remove($name);
            }
        }
    }
}
