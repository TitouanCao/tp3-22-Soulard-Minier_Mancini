<?php

class Source {
  public $name;
  public $src;
  public $color;
  public $twitter;

  function __construct($name, $src, $color, $twitter = null) {
    $this->name = $name;
    $this->src = $src;
    $this->twitter = $twitter;
    $this->color = $color;
  }

  function is_correct() {
    if ($this->name == null || $this->name == "") return false;
    if ($this->src == null || $this->src == "") return false;
    return true;
  }

  function has_color() {
    return $this->color != null && $this->color != "" && (preg_match("#\#[a-f0-9]{6}#", $this->color) ||
    in_array($this->color, $_SESSION["colors"]) ||
    in_array($this->color, array_map('mb_strtolower', $_SESSION["colors"])));
  }

  function has_twitter() {
    return $this->twitter != null && $this->twitter != "";
  }

  function to_string() {
    return "\"".$this->name."\", \"".$this->src."\", \"".$this->color."\"";
  }

  function has_conflict($source) {
    if ($this->name == $source->name || $this->src == $source->src) {
      return true;
    }
    else return false;
  }

  function is_same_feed($source) {
    return $this->src == $source->src;
  }

  function is_same_name($source) {
    return $this->name == $source->name;
  }

}

?>