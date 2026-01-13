<?php
namespace BlueFission\Automata\Language;

class Preparer {
	public $noise = ['that', 'the', 'we', 'here', 'to', 'we', 'and', 'it', 'is', 'of', 'a', 'not', 'for', 'have', 'in', 'on', 'with', 'be', 'will', 'are', 'they', 'why', 'what', 'who', 'where', 'when', 'i', 'which', 'there', 'was', 'have', 'you', 'your', 'why', 'do', 'or', 'my', 'not', 'can', 'this', 'no', 'as', 'all', 'was', 'so', 'its', 'our', 'by', 'did', 'an', 'i\'m', 'it\'s'];
	public $blacklist = [];

	public function setBlacklist($blacklist)
	{
		$this->blacklist = $blacklist;
	}

	public function tokenize($input, $boundary = '/\s/') {
		$input = stripslashes($input);
		$input = strip_tags($input);
		$input = trim($input, '\'"');	        		
		$chr_map = array(
		   // Windows codepage 1252
		   "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
		   "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
		   "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
		   "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
		   "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
		   "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
		   "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
		   "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark

		   // Regular Unicode     // U+0022 quotation mark (")
		                          // U+0027 apostrophe     (')
		   "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
		   "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
		   "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
		   "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
		   "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
		   "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
		   "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
		   "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
		   "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
		   "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
		   "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
		   "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
		);
		$chr = array_keys  ($chr_map); // but: for efficiency you should
		$rpl = array_values($chr_map); // pre-calculate these two arrays
		$input = str_replace($chr, $rpl, html_entity_decode($input, ENT_QUOTES, "UTF-8"));
		$input = ContractionNormalizer::normalize($input);
		$input = str_replace(['?', '!', '.', ',', '"', '\''], '', $input);

		$array = preg_split($boundary, strtolower($input), -1, PREG_SPLIT_NO_EMPTY);
		$array = array_diff($array, $this->noise);
		$array = array_diff($array, $this->blacklist);

		return $array;
	}
}
