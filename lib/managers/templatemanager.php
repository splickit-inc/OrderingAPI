<?php 
class TemplateManager {

  private $mustache_engine;
  
  function TemplateManager($mustache_engine) {
    if(isset($mustache_engine)) {
      $this->mustache_engine = $mustache_engine;
    } else {
      $this->mustache_engine = new Mustache_Engine();
    }
  }
  
  public function populateTemplate($template, $data) {    
    return $this->mustache_engine->render($template, $data);
  }
}
