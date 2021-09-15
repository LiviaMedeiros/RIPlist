<?php declare(strict_types = 1);
class Plist {
	private $doc;
	private $root;
	private function get_array(DOMElement $node, array $res = []) {
		for ($i = $node->firstChild; $i != null; $i = $i->nextSibling)
			if ($i->nodeType == XML_ELEMENT_NODE)
				$res[] = $this->get_data($i);
		return $res;
	}
	private function get_dict(DOMElement $node, array $res = []) {
		for ($i = $node->firstChild; $i != null; $i = $i->nextSibling)
			if ($i->nodeName == "key") {
				for ($j = $i->nextSibling; $j->nodeType == XML_TEXT_NODE; $j = $j->nextSibling);
				$res[$i->textContent] = $this->get_data($j);
			}
		return $res;
	}
	private function get_data(DOMElement $node) {
		return match(strtolower($node->nodeName)) {
			'real' => floatval($node->textContent),
			'integer' => intval($node->textContent),
			'string', 'date' => $node->textContent, // date should be in ISO 8601
			'true' => true,
			'false' => false,
			'data' => base64_decode($node->textContent), // expect raw base64 in <string> as well
			'array' => $this->get_array($node),
			'dict' => $this->get_dict($node),
			default => throw new Exception("Unknown tag [$node->nodeName]")
		};
	}

	function __construct(string $file) {
		$this->doc = new DOMDocument();
		$this->doc->preserveWhiteSpace = false;
		is_readable($file) && $this->doc->load($file) || throw new Exception("Bad file [$file]");
		for ($this->root = $this->doc->documentElement->firstChild; $this->root->nodeType == XML_TEXT_NODE; $this->root = $this->root->nextSibling);
	}
	function __invoke(bool $xml = false) {
		return $xml ? $this->doc : $this->get_data($this->root);
	}
}
