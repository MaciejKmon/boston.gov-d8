<?php

namespace Drupal\bos_email\Templates;

use Drupal\bos_email\Controller\EmailControllerBase;
use Drupal\Core\Controller\ControllerBase;

/**
 * Template class for Postmark API.
 */
class MetrolistListingNotification extends ControllerBase implements EmailControllerBase {

  /**
   * Template for plain text message.
   *
   * @param string $message_txt
   *   The message sent by the user.
   * @param string $name
   *   The name supllied by the user.
   * @param string $from_address
   *   The from address supplied by the user.
   * @param string $url
   *   The page url from where form was submitted.
   */
  public static function templatePlainText(&$emailFields) {

    //TODO: remove after testing
    $emailFields["bcc"] = "david.upton@boston.gov, james.duffy@boston.gov";

    $emailFields["tag"] = "metrolist listing";

    $vars = self::_getRequestParams();
    $decisions = "";
    $new = "";
    if ($vars["new"]) {
      $new = "new ";
      $decisions = "Updates: " . implode("\n         ", $vars["decisions"]);
    }
    $emailFields["TextBody"] = "
A ${new}Metrolist Listing form submission has been completed on boston.gov:\n
Submitted on: ${vars["completed"]}
Submitted By: ${vars["contact_name"]}
Listing Contact Company: ${vars["contact_company"]}
Contact Email: ${vars["contact_email"]}
Contact Phone: ${vars["contact_phone"]}
Property Name: ${vars["property_name"]}
Property Address: ${vars["street_address"]}, ${vars["city"]}, ${vars["zip_code"]}
${decisions}
View Development: https://boston-dnd.lightning.force.com/lightning/r/Development__c/${vars["developmentsfid"]}/view${vars["developmentsfid"]}\n\n
--------------------------------
View Pending Development Units: https://boston-dnd.lightning.force.com/lightning/o/Development_Unit__c/list?filterName=00B0y00000A4vQ3EAJ
This submission was made via the Metrolist Listing form on Boston.gov (" . urldecode($emailFields['url']) . ")
--------------------------------
";
  }

  /**
   * Template for html message.
   *
   * @param string $message_html
   *   The HTML message sent by the user.
   * @param string $name
   *   The name supllied by the user.
   * @param string $from_address
   *   The from address supplied by the user.
   * @param string $url
   *   The page url from where form was submitted.
   */
  public static function templateHtmlText(&$emailFields) {

    $vars = self::_getRequestParams();

    $weblink = "";
    if (!empty($vars["website_link"])) {
      $weblink = "<p class='txt'><a href='${vars["website_link"]}'>Property Link</a></p>";
    }

    $contact = "${vars["contact_name"]}";
    if (!empty($vars["contactsfid"])) {
      $contact = "<a href='https://boston-dnd.lightning.force.com/lightning/r/Contact/${vars["contactsfid"]}/view'>${vars["contact_name"]}</a>";
    }

    $development = $vars["property_name"];
    if (!empty($vars["developmentsfid"])) {
      $development = "<a href='https://boston-dnd.lightning.force.com/lightning/r/Development__c/${vars["developmentsfid"]}/view'>${vars["property_name"]}</a>";
    }

    $head = "A Metrolist Property has been updated using the Metrolist Listing form on boston.gov";
    if ($vars["new"]) {
      $head = "A new Metrolist Listing form submission has been completed on boston.gov";
    }

    $decisions = "";
    if (!empty($vars["decisions"])) {
      $decisions = "<tr><td><span class='txt-b'>Updates:</span></td><td>" . implode("<br>", $vars["decisions"]);"</td></tr>";
    }

    $html = "
<img class='ml-icon' height='34' src='https://assets.boston.gov/icons/metrolist/metrolist-logo_email.png' />\n
<p class='txt'>${head}</p>\n
<p class='txt'><table class='moh-signature' cellpadding='0' cellspacing='0' border='0'>\n
<tr><td><span class='txt-b'>Submitted on:</span></td><td>${vars["completed"]}</td></tr>\n
<tr><td><span class='txt-b'>Submitted By:</span></td><td>${contact}</td></tr>\n
<tr><td><span class='txt-b'>Listing Contact Company:</span></td><td>${vars["contact_company"]}</td></tr>\n
<tr><td><span class='txt-b'>Contact Email:</span></td><td>${vars["contact_email"]}</td></tr>\n
<tr><td><span class='txt-b'>Contact Phone:</span></td><td>${vars["contact_phone"]}</td></tr>\n
<tr><td><span class='txt-b'>Property Name:</span></td><td>${development}</td></tr>\n
<tr><td><span class='txt-b'>Property Address:</span></td><td>${vars["street_address"]}, ${vars["city"]}, ${vars["zip_code"]}</td></tr>\n
${decisions}\n
</table></p>\n
${weblink}\n
<hr>
<p class='txt'><a href='https://boston-dnd.lightning.force.com/lightning/o/Development_Unit__c/list?filterName=00B0y00000A4vQ3EAJ'>View Pending Development Units</a></p>\n
<p class='txt'>This submission was made via the <a href='${emailFields['url']}'>Metrolist Listing Form</a> on Boston.gov.</p>\n\n
<hr>\n
";

    $emailFields["HtmlBody"] = self::_makeHtml($html, $emailFields["subject"]);

  }

  /**
   * Fetch the default email css (can extend it here if needed.
   *
   * @return string
   */
  public static function _css() {
    $css = EmailTemplateCss::getCss();
    return "<style type='text/css'>
        ${css}
        .ml-icon {
          height: 34px;
        }
        table.moh-signature {
          border: none;
          border-collapse: collapse;
        }
        table.moh-signature tr,
        table.moh-signature td {
          padding: 3px;
          border-collapse: collapse;
        }
      </style>";
  }

  /**
   * Helper function to extract and process request parameters.
   *
   * @return array
   */
  public static function _getRequestParams() {
    $request = \Drupal::request();
    $output = [
      "sid" => $request->get("sid",""),
      "serial" => $request->get("serial",""),
      "new" => ($request->get("select_development", "") == "new"),
      "property_name" => $request->get("property_name",""),
      "completed" => gmdate("Y-m-d H:i", $request->get("completed","")),
      "new_contact" => ($request->get("select_contact", "") == "new"),
      "contact_name" => $request->get("contact_name",""),
      "contact_company" => $request->get("contact_company",""),
      "contact_email" => $request->get("contact_email",""),
      "contact_phone" => $request->get("contact_phone",""),
      "street_address" => $request->get("street_address",""),
      "city" => $request->get("city",NULL) ?: $request->get("neighborhood","Boston"),
      "zip_code" => $request->get("zip_code",""),
      "website_link" => $request->get("website_link",""),
      "developmentsfid" => $request->get("developmentsfid",""),
      "contactsfid" => $request->get("contactsfid",""),
      "decisions" => [],
    ];

    if (!$output["new"]) {

      if (!empty( $request->get("update_building_information"))) {
        $output["decisions"][] = "- Building information";
      }
      if (!empty( $request->get("update_public_listing_information"))) {
        $output["decisions"][] = "- Public-listing information";
      }
      if (!empty( $request->get("update_unit_information")) && !empty( $request->get("add_additional_units"))) {
        $output["decisions"][] = "- Unit information";
      }
    }

    if (!$output["new_contact"] && !empty( $request->get("update_my_contact_information"))) {
        $output["decisions"][] = "- Contact information";
    }

    return $output;
  }

  /**
   * Ensures an html string is top and tailed as a full html message.
   *
   * @param string $html Html message to format
   * @param string $title Title (in head) for the html object
   *
   * @return string
   */
  public static function _makeHtml(string $html, string $title) {
    // check for complete-ness of html
    if (stripos($html, "<html") === FALSE) {
      $output = "<html>\n<head></head>\n<body>${html}</body>\n</html>";
    }

    // Add a title
    $output = preg_replace(
      "/\<\/head\>/i",
      "<title>${title}</title>\n</head>",
      $output);
    // Add in the css
    $css = self::_css();
    $output = preg_replace(
      "/\<\/head\>/i",
      "${css}\n</head>",
      $output);

    return $output;

  }
}