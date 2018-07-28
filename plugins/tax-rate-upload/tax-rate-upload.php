<?php
/**
Plugin Name: Tax Rate Upload
Plugin URI: http://wordpress.org/extend/plugins/tax-rate-upload/
Description: This is a plugin to upload a tax rate excell sheet and populate woocommerce zip code tax rates. 
Author: Adam Bowen
Version: 2.4.5
Author URI: http://www.pnw-design.com/
*/
add_action( 'admin_menu', 'tax_rate_upload_menu' );
function tax_rate_upload_menu() {
  add_options_page( 'Tax Rate Upload', 'Tax Rate Upload', 'manage_options', 'tax-rate-upload', 'tax_rate_upload_options' );
}
function tax_rate_upload_options() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
?>
<script language="JavaScript">
jQuery(document).ready(function() {
jQuery( "#addRates" ).click(function() {
jQuery( ".addloading" ).slideDown(500);
});

jQuery('.addRatesButton').attr('disabled',true);
  jQuery('input:file').change(
      function(){
          if (jQuery(this).val()){
              jQuery('.addRatesButton').removeAttr('disabled'); 
          }
          else {
              jQuery('.addRatesButton').attr('disabled',true);
          }
});

});

</script>

<style type="text/css">
  .addRatesButton, .deleteRatesButton, .updateTax, .deleteState, .updatePriority {
    background-color: #21759B !important;
    background-image: linear-gradient(to bottom, #2A95C5, #21759B);
    border-color: #21759B #21759B #1E6A8D !important;
    border-radius: 4px 4px 4px 4px !important;
    border-style: solid !important;
    border-width: 1px !important;
    box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset;
    color: #FFFFFF !important;
    cursor: pointer;
    height: 24px;
    padding: 0 10px 1px;
    text-decoration: none;
    text-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
}
  .icon32 {
  background-image: url("../wp-content/plugins/woocommerce/assets/images/icons/woocommerce-icons.png") !important;
  background-position: -362px -5px;
  background-size: auto auto !important;
  margin-top: 7px;
  }
  h1{
  color:#A3678C
  }
  .message {
  color: Green;
  font-size: 18px;
  }
.left {
    float: left;
    width: 640px;
}
.right {
    border-color: #9B5B82;
    border-radius: 10px 10px 10px 10px;
    border-style: solid;
    border-width: 26px 3px 3px;
    float: right;
    margin-bottom: 15px;
    margin-top: 4px;
    padding: 10px;
    width: 350px;
}
  .right h3 {
  color: white;
  font-size: 1.17em;
  margin-top: -29px;
  }
  .right li a {
  display: block;
  margin-bottom: 4px;
  margin-left: 20px;
  }
  .iconImage {
  float: left;
  padding: 1px;
  }
.instruction, .import, .delete, .taxName, .currentRates, .donate, .shipping {
    border-color: #9B5B82;
    border-radius: 10px 10px 10px 10px;
    border-style: solid;
    border-width: 26px 3px 3px;
    padding: 7px;
    width: 620px;
    margin-top: 5px;
}
.instruction h3, .import h3, .delete h3, .taxName h3, .currentRates h3, .donate h3, .shipping h3{
    color: #FFFFFF;
    font-size: 1.17em;
    margin-top: -29px;
}
.wrap {
    margin: 4px 15px 0 0;
    max-width: 1040px;
}
</style>
<div id="icon-woocommerce" class="icon32 icon32-woocommerce-settings">
</div>
<h1>Tax Rate Upload</h1>
<div class="wrap">
  <div class="left">
<div class="instruction">
    <h3>Instructions</h3>
    <p class="info">Download your states tax rate table from <a href="http://www.taxrates.com/state-rates/" target="_blank">taxrates.com</a> and then use the browse button below to browse for the csv file you downloaded, after browsing for the file, upload it by clicking on Add Tax Rates button.</p>
    <p>If you already have existing Rates for a State ex: WA State that you need to add/update rates for you will need to first use the Delete All Tax Rates Button. This is to prevent issues with existing rates.</p>
    <p>Do not delete Rates if you are adding a new State that does not have any existing rates... You can see below which states you currently have rates for and how many rates for each state.</p>
</div>
<div class="import">
    <h3>Import your csv file</h3>
    <?php
global $wpdb;
$taxTable = $wpdb->prefix . "woocommerce_tax_rates";
$taxTableLocation = $wpdb->prefix . "woocommerce_tax_rate_locations";
if(isset($_POST['submit']))
{
    $file = $_FILES['file']['tmp_name'];
    $old_val = get_option('woocommerce_tax_rates'); $new_val = array();// if the key doesn't exist return an empty array
    
    $new_val = is_string($new_val) ? array() : $new_val;// replace potentially corrupt serialized data
    $handle = fopen($file, "r");
    fgetcsv($handle,1000,",");// remove first row/header files must have a header to not lose the first row of data
    while(($fileop = fgetcsv($handle,1000,",")) !==false)
    {  
      // just a test to see what it is reading and it is grabbing the correct data from csv file but not inserting to database
      $data = array();
      $data['tax_rate_state'] = $fileop[0];
      $data['tax_rate_postcode'] = array($fileop[1]);
      $data['tax_rate'] = $fileop[4] * 100;
      $data['tax_rate_country'] = 'US';
      $data['tax_rate_shipping'] = '1';
      $data['tax_rate_priority'] = '0';
      $data['tax_rate_compound'] = '0';
      $data['tax_rate_class'] = '';
      $new_val[] = $data;
      
      $country = 'US';
      $state = $fileop[0];
      $rate = $fileop[4] * 100;
      $city = $fileop[2];
      $postcode = $fileop[1];
    // Inserts values into woocommerce_tax_rates
    $wpdb->query("INSERT INTO $taxTable (tax_rate_country, tax_rate_state, tax_rate, tax_rate_name) VALUES ('$country', '$state', '$rate', '$city')");
    

    $thelastId = $wpdb->get_results("SELECT tax_rate_id FROM $taxTable ORDER BY tax_rate_id DESC LIMIT 1;");

    //Gets the ID of the last inserted value in wpdb
    $lastid = $wpdb->insert_id;

    //Gets the ID of the last inserted value
    /*$rateid = mysql_insert_id();*/
    
    //Inserts zip code value into wp_woocommerce_tax_rate_locations
    $wpdb->query("INSERT INTO $taxTableLocation (tax_rate_id, location_code, location_type) VALUES ('$lastid', '$postcode', 'postcode')");
    
    //Inserts city value into wp_woocommerce_tax_rate_locations
   
  
    }
    
    $qid = update_option('woocommerce_tax_rates', $new_val, $old_val);
      if($qid)
      {
        echo '<p class="message">';
        echo 'Tax Rates Added';
        echo '</p>';
      }
     
      else
      {
        echo '<p class="message">';
        echo 'Tax Rates Updated';
        echo '</p>';
        echo $qid;
      }
}
?>    
    <form method="post" enctype="multipart/form-data" action="options-general.php?page=tax-rate-upload" >
      <img class="addloading" id="addloading" src="<?php echo home_url() ?>/wp-content/plugins/tax-rate-upload/loadingBar.gif" style="display: none"/>
    </br>
      <label for="file">Browse to your csv file:</label>
      <br />
      <input class="browse" type="file" name="file">
        <br />
        <br />
        <input class="addRatesButton" type="submit" name="submit" id="addRates" value="Add Tax Rates!"/>
      </form>
</div>
<!--  -->
<div class="shipping">
    <h3>Change All Priority to 1?</h3>
<?php
if(isset($_POST['Priority']))
{
    $option = $_POST['priorityOption'];
    $wpdb->query("UPDATE $taxTable SET tax_rate_priority = ('$_POST[priorityOption]');");
    echo '<p class="message">';
    echo 'Updated Priority';
    echo '</p>';
}
?>
    <form method="post" enctype="multipart/form-data" action="options-general.php?page=tax-rate-upload" >
      <select name="priorityOption">
      <option>Select Option</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
      </select>
      <input class="updatePriority" type="submit" name="Priority" value="Submit"/>
    </form>
  </div>
  <!--  -->
<div class="shipping">
    <h3>Charge tax on shipping?</h3>
<?php
if(isset($_POST['shippingYes']))
{
    $option = $_POST['taxShip'];
    $wpdb->query("UPDATE $taxTable SET tax_rate_shipping = ('$_POST[taxShip]');");
    echo '<p class="message">';
    echo 'Updated Shipping Tax';
    echo '</p>';
}
?>
    <form method="post" enctype="multipart/form-data" action="options-general.php?page=tax-rate-upload" >
      <select name="taxShip">
      <option>Select Option</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
      </select>
      <input class="updateTax" type="submit" name="shippingYes" value="Submit"/>
    </form>
  </div>
<div class="taxName">
    <h3>Change Tax Name</h3>
    <p>This will be the name that shows up in the cart/checkout, anytime you upload a new tax csv by default it will show the city or county name...</p>
    <p>EXAMPLE: If you want it to display Tax type Tax, if you would like it to display Sales Tax type Sales Tax</p>
<?php
if(isset($_POST['taxNameChange']))
{
    $wpdb->query("UPDATE $taxTable SET tax_rate_name = ('$_POST[taxName]');");
    echo '<p class="message">';
    echo 'Tax Name Updated';
    echo '</p>';
}
?>
    <form method="post" enctype="multipart/form-data" action="options-general.php?page=tax-rate-upload" >
      <input class="updateTaxInput" type="text" name="taxName"/>
      <input class="updateTax" type="submit" name="taxNameChange" value="Update Tax Name"/>
    </form>
  </div>
  <div class="currentRates">
    <h3>Current Zip Code Stats</h3>
    <p>
      <?php
      $result = $wpdb->get_results("SHOW TABLE STATUS LIKE '$taxTable';");
      foreach ($result as $data) {
      echo 'Last Updated On: ', $data->Update_time;
      }
    ?>
    </p>
    <p>
      <?php
      $result = $wpdb->get_results("SHOW TABLE STATUS LIKE '$taxTable';");
      foreach ($result as $data) {
      echo 'Total Number Of Zip Code Rates: ', $data->Rows;
if(isset($_POST['delete']))
{
    $wpdb->query("TRUNCATE TABLE $taxTable;");
    $wpdb->query("TRUNCATE TABLE $taxTableLocation;");
    echo '<p class="message">';
    echo 'All Rates Removed';
    echo '</p>';
}
?>
<?php if ($data->Rows > 0){
  ?>
    <form method="post" enctype="multipart/form-data" action="options-general.php?page=tax-rate-upload" >
      <input class="deleteRatesButton" type="submit" name="delete" value="Delete All Tax Rates"/>
    </form>
  <?php
}
else{
}
?>
<?php
      }
    ?>
    </p>
    <p>
      <?php    
      echo 'Total Number Of Zip Code Rates By State(s)';   
      if(isset($_POST['deleteState']))
      {
          $state = $_POST['tax_rate_state'];  
          $wpdb->query("DELETE FROM $taxTable WHERE tax_rate_state = '$state'");
          $wpdb->query("DELETE FROM $taxTableLocation WHERE NOT EXISTS (SELECT * FROM $taxTable WHERE wp_woocommerce_tax_rate_locations.tax_rate_id = wp_woocommerce_tax_rates.tax_rate_id)");
          echo '<p class="message">';
          echo "$state";
          echo ' Tax Rates Removed';
          echo '</p>';
      }   
      
      $result = $wpdb->get_results("SELECT tax_rate_state, count(*) AS tagCount FROM $taxTable GROUP BY tax_rate_state", ARRAY_A);
      if($result === FALSE) {
        echo $wpdb->prefix;
      echo mysql_error();
      $sql = "SHOW TABLES LIKE '%'";
      $results = $wpdb->get_results($sql);
      foreach($results as $index => $value) {
      foreach($value as $tableName) {
        echo $tableName . '<br />';
      }
      }
      }
      foreach($result as $row){  
      echo '<ul>';
          echo '<form method="post" enctype="multipart/form-data" action="options-general.php?page=tax-rate-upload"><li>' .$row['tax_rate_state']. ' ' .$row['tagCount']. ' ' .'Zip Codes'. '<input type="hidden" value="'.$row['tax_rate_state'].'" name="tax_rate_state"><input class="deleteState" type="submit" name="deleteState" value="Delete"/>'. '</li></form>';
      echo '</ul>';
      }
      ?>
    </p>
  </div>
    <br />
    <div class="donate">
    <h3>Donate</h3>
    <p>
      Please make a donation to help support this plugin and keep it free. If this plugin has saved you time, or I have provided tech support please consider making a donation.
    </p>
    <form method="post" action="https://www.paypal.com/cgi-bin/webscr">
      <input type="hidden" value="_s-xclick" name="cmd">
        <input type="hidden" value="4T3TLUT6KUQ3C" name="hosted_button_id">
          <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" name="submit" alt="PayPal - The safer, easier way to pay online!">
            <img width="1" height="1" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" alt="">
    </form>
  </div>
  </div>
  <div class="right">
    <h3>About this plugin</h3>
    <ul>
    <li>
      <img class="iconImage" src="http://www.pnw-design.com/pnwdesign/wp-content/themes/Test/Images/favicon.png"/><a href="http://www.pnw-design.com/wordpress-plugins/">Plugin Homepage</a>
      <img class="iconImage" src="http://www.pnw-design.com/pnwdesign/wp-content/themes/Test/Images/favicon.png"/><a href="mailto:adambowen@pnw-design.com">Suggest a new feature</a>
      <img class="iconImage" src="http://www.pnw-design.com/pnwdesign/wp-content/themes/Test/Images/favicon.png"/><a href="mailto:adambowen@pnw-design.com">Report a bug</a>
      <img class="iconImage" style="width:16px; height:16px;" src="http://www.pnw-design.com/pnwdesign/wp-content/themes/Test/Images/paypal.png"/><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4T3TLUT6KUQ3C">Donate with paypal</a>
      <img class="iconImage" style="width:16px; height:16px;" src="http://www.pnw-design.com/pnwdesign/wp-content/themes/Test/Images/amazon.png"/><a href="http://amzn.com/w/1Z8O2JM7GNNKP">My Amazon Wishlist</a>
    </li>
    </ul>
  </div>
</div>
<?php
}