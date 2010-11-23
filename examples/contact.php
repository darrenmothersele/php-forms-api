<?php 

// if sessions are enabled then the form uses a token for extra security against CSRF
session_start();
require '../src/form.php';

// Generate a simple contact form
$form = new cs_form(array('form_id' => 'contact'));
$form->add_field('name', array(
  'type' => 'textfield',
  'validate' => array('required'),
  'preprocess' => array('trim'),
  'title' => 'Your name',
));
$form->add_field('email', array(
  'type' => 'textfield',
  'validate' => array('required', 'email'),
  'title' => 'Your email address',
));
$form->add_field('message', array(
  'type' => 'textarea',
  'postprocess' => array('xss'),
  'title' => 'Your message',
));
$form->add_field('submit', array(
  'type' => 'submit',
));

// Submit function to call when the form is submitted and passes validation.
// This is where you would send the email (using PHP mail function) 
// as this is not a real example I'm just outputting the values for now.
function contact_submit(&$form) {
  $form_values = $form->values();
  print_r($form_values);
  // Reset the form if you want it to display again.
  // $form->reset();
} 


?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Example contact form</title>
	<style>
	label { display: block; }
	span.required { color: red; }
	.error { background: #FFA07A; }
	</style>
</head>

<body>
<a href="contact.php">Go back</a>
<pre style="font-size:10px;"><?php $form->process(); ?></pre>
<h1>Example Form</h1>
<?php if ($form->is_submitted()): ?>
  <!-- if the form was reset during the submit handler we would never see this -->
  <p>Thanks for submitting the form.</p>
<?php else: ?>
  <?php print $form->render(); ?>
<?php endif; ?>
<pre style="font-size:10px;"><?php // print_r($form); ?></pre>
</body>
</html>
