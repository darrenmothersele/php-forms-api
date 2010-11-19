# A Forms API Library for PHP 

## Important notices

The main repository for this code is github. If you have received the file 
from another location please check this URL for the latest version:

    https://github.com/darrenmothersele/php-forms-api

For bug reports, feature requests, or support requests, please start a case
on the GitHub case tracker:

    https://github.com/darrenmothersele/php-forms-api/issues

## Roadmap

 * Finish implementation of options such as _disabled_, _attributes_, 
 * Offer a wider range of examples
 * Complete the javascript functionality for collapsible fieldsets and the
   password strength meter for password fields.
 * Complete unit tests.
 * AJAX support for field submission and file uploads.
 * Provide a better default theme and a methodology for defining themes.
 * Implement new field types such as button, dates
 * Add support for masked fields, possibly using a jquery plugin.

## Introduction 

This Forms API for PHP takes all the hard work out of building, validating, and
processing HTML forms in your PHP applications. The Forms API handles all 
common form elements and validation rules, and it's object-oriented design 
can be easily expanded with new fields and validation rules.

## Basic forms workflow

The basic workflow of this forms API is as follows:

 * Create new form object
 * Add fields to form
 * Process form
 * Display form
 
It may not be obvious at first why you process the form before you display the 
form. This is done because, when using this library, usually the same page 
(or controller in MVC) will handle the form submissions as well as displaying 
the form. This is useful because if the form fails validation you are in the
right place to display the form again with error messages and prompts to 
correct mistakes and resubmit the form.

Let's look at the steps of the form workflow in more detail:

### Create new form object

The form object is the main starting point for using this Forms API. When you 
create a form you can provide some options in an array to override the default
options. By default the form submits back to itself, this is the most useful
configuration because you can use the same object to validate and process the
submitted form.

NB: You can use the default form ID of cs_form if you only have one form, but 
if you have multiple forms then you must override this value. The form ID is 
used to generate the HTML ID tag, so must be unique for valid HTML. It is also 
used in generating the default name of the php function to use when the form is 
submitted.  

NB: If you are using file uploads in your form then you must specify the 
enctype. For more information see the [Form objects] section of the API 
reference.

### Add fields to your form

You can add any number of fields to a form, but the only required field is a 
submit button so that the user can submit the form. 

You can nest fields inside fieldsets if you want to break up longer forms, or 
hide advanced options that are not commonly used. Javascript (jQuery) is 
provided to support collapsible fieldsets.

### Processing forms

This is what happens when you submit a form for processing:

 * Check the incoming request to see if form has been submitted. If this page
   request is the result of a submission then copy the values from the request
   into the form.
 * Run any field processors defined to run before validation.
 * Validate the form. If the form was not submitted fail validation without 
   checking anything so the form displays for the first time. If this request
   was the result of submitting this form run all field validators. If any of
   the validators fail set error conditions in the form.
 * If the form was submitted and passed validation run any field processors
   defined to run after validation. Then check if the submit handler is exists
   as a php function. 
 * If the submit handler is found it is called, passing in 
   the final form object (which can be used to extract the values).

### Display the form

Displaying the form is actually the final step. If there is no form submission
found in the request then this is the first time it is displayed and it has 
been populated with the default values. Alternatively the form may be being 
displayed as the result of a request that has failed validation. In this case
extra information is displayed about the error conditions.


## A walkthrough of the contact form example 

You will find the source code for this example in this file: example/contact.php



## Form API reference

To use this Form API in your projects you just need to include the main
form.php file as follows:

    require 'form.php';

Be sure to correct the path to form.php if it is not in the same folder as 
your script. The static assets (images, css and javascript) should by default 
be in a folder called assets. You may need to set the BASE_PATH configuration
option if this changes.

### Form objects 

This example array shows all valid options for form objects, and their 
default values:

    $options = array(
      'form_id' => 'cs_form',
      'action' => '',
      'attributes' => array(),
      'method' => 'post',
      'prefix' => FORMS_DEFAULT_PREFIX, // set in configuration
      'suffix' => FORMS_DEFAULT_SUFFIX, // set in configuration
      'submit' => FORM_ID .'_submit', 
    );

If you include a file upload field in your form then you must set the encoding
type to multipart data, by setting an attribute like this:

    $options['attributes']['enctype'] = 'multipart/form-data';

### Field objects

Here are the available fields and their options:

#### Text fields

#### Password fields

#### Text areas

#### Submit buttons

#### Select lists

#### Radio buttons

#### Checkboxes

#### Hidden values

#### Markup

#### Files

#### Field sets

### Validators reference

Required, Max Length, Min Length, Exact Length, Alpha, Alpha-numeric,
Alpha-numeric with dashes, Numeric, Integer, Field matching, Email,
File extension, File not exists, File max size, File type.

### Processors reference

For security reasons, all user submitted data should be filtered before use.
This Forms API library helps you by providing some useful filtering tools that
will process submitted input. You can use these in your submit functions as and
when you need them, or you can have them run automatically during forms
processing. You can specify processors to run on a field before or after the
field is passed for validation.  

















