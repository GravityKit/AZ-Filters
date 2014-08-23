<?php
/**
 * French Alphabet
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
		'j', 
		'k', 
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
		'w', 
		'x', 
		'y', 
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