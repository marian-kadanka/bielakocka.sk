<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function bk_send_json( $msg, $success = true ) {
	echo json_encode( array( 'success' => $success, 'msg' => $msg ) );
	die;
}

if ( ! headers_sent() ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Expires: Wed, 23 Mar 1982 21:08:00 GMT' );
	header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
}

foreach ( array( "obrazky", "prilohy" ) as $upload ) {
	$size = 0;
	for ( $i = 0; $i < count( $_FILES[ $upload ]['size'] ); $i++ ) {
		$size += $_FILES[ $upload ]['size'][ $i ];
	}
	if ( $size > 10 * 1024 * 1024 ) {
		bk_send_json( "Priložené súbory prekračujú hranicu 10 MB!", false );
	}
}

require 'vendor/autoload.php';

$mail = new PHPMailer();
$mail->CharSet = PHPMailer::CHARSET_UTF8;;
$mail->setFrom( "noreply@bielakocka.sk", $_POST["meno-priezvisko-nominujuci"] );
$mail->addReplyTo( $_POST["email-nominujuci"], $_POST["meno-priezvisko-nominujuci"] );
$mail->addAddress( "dominika@vsg.sk" );
$mail->addAddress( "rada.galerii.slovenska@gmail.com" );
$mail->Subject = "Nominačný formulár Biela kocka";
$mail->WordWrap = 80;

$form = array(
	"Nominovaný",
	"meno-priezvisko-nominovany" => "Meno a priezvisko nominovaného",
	"institucia" => "Inštitúcia",
	"email-nominovany" => "E-mail",
	"Projekt",
	"kategoria" => "Kategória",
	"nazov-projektu" => "Názov nominovaného projektu",
	"miesto-termin" => "Miesto a termín realizácie / vydavateľ",
	"popis" => "Celý popis projektu",
	"Nominujúci",
	"meno-priezvisko-nominujuci" => "Meno a priezvisko",
	"email-nominujuci" => "E-mail",
	"",
	"moze-kontaktovany" => "Na vyžiadanie poroty môže byť nominant dodatočne kontaktovaný",
	"suhlas" => "Súhlasím so spracovaním osobných údajov v zmysle §5 písm. a) a v zmysle § 14 zákona č. 18/2018 Z. z. o ochrane osobných údajov a o zmene a doplnení niektorých zákonov",
);

$cats = array( 
	"edicny-projekt" => "Edičný projekt",
	"kuratorsky-projekt" => "Kurátorský projekt",
	"edukacny-projekt" => "Edukačný projekt",
	"iny-odborny-projekt" => "Iný odborný projekt",
	"nezavisly-projekt" => "Nezávislý projekt",
);

$msg = "";

foreach ( $form as $key => $field ) {
	if ( is_numeric( $key ) ) {
		if ( ! empty( $field ) ) {
			$msg .= "<br><strong>" . $field . "</strong><br>";
		} else {
			$msg .= "<br>";
		}
	} elseif ( array_key_exists( $key, $_POST ) ) {
		if ( $key === "kategoria" ) {
			$value = $cats[ $_POST[ $key ] ];
		} elseif ( $key === "moze-kontaktovany" ) {
			$value = ( $_POST[ $key ] === "moze-kontaktovany" ) ? "áno" : "nie";
		} elseif ( $key === "suhlas" ) {
			$value = ( $_POST[ $key ] === "suhlas" ) ? "áno" : "nie";
		} else {
			$value = substr( strip_tags( $_POST[ $key ] ), 0, 2000 );
		}
		$msg .= $field . ": " . $value . "<br>";
	}
}

$mail->msgHTML( $msg );
$mail->IsHTML( true );

$uid = uniqid();
mkdir( "uploads/" . $uid, 0777, true );
file_put_contents( "uploads/" . $uid . "/form.html", $msg );

foreach ( array( "obrazky", "prilohy" ) as $upload ) {
	for ( $i = 0; $i < count( $_FILES[ $upload ]['tmp_name'] ); $i++ ) {
		$filename = $_FILES[ $upload ]['name'][ $i ];
		if ( ! empty( $filename ) ) {
			$uploadfile = "uploads/" . $uid . "/" . $filename;
			if ( move_uploaded_file( $_FILES[ $upload ]['tmp_name'][ $i ], $uploadfile ) ) {
				if ( ! $mail->addAttachment( $uploadfile, $filename ) ) {
					bk_send_json( "Nepodarilo sa priložiť súbor " . $filename, false );
				}
			} else {
				bk_send_json( "Nepodarilo sa presunúť súbor " . $uploadfile, false );
			}
		}
	}
}

if ( ! $mail->send() ) {
	bk_send_json( "Formulár sa nepodarilo odoslať, chyba: " . $mail->ErrorInfo, false );
} else {
	bk_send_json( "Formulár bol úspešne odoslaný, ďakujeme." );
}
