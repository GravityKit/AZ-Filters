<?php
/**
 * Italian Alphabet
 *
 * @since 1.0.0
 */
function alphabet_letters() {
	$alphabets = array(
		'a', 
		'b', 
		'c', 
		'd', 
		'e', 
		'f', 
		'g', 
		'h', 
		'i', 
		'l', 
		'm', 
		'n', 
		'o', 
		'p', 
		'q', 
		'r', 
		's', 
		't', 
		'u', 
		'v', 
		'z'
	);
	return sort($alphabets);
}

function first_letter() {
	return 'a';
}

function last_letter() {
	return 'z';
}

?>