<?php 
/*
Plugin Name: CSS Submittals
Plugin URI:
Description:
Author: Peter K Morrison
Version: 1.1
Author URI: http://contractorsteelsystems.com/
*/
use Spipu\Html2Pdf\Html2Pdf;

class CSSSubmittals {
	public $tabs = array();
	public $pages = array();
	private $names = array(
		'1-1_4-Flange' => '1 1/4" Flange',
		'1-1_4-Leg' => '1 1/4" Leg',
		'2-Leg' => '2" Leg',
		'1-5_8-Flange' => '1 5/8" Flange',
		'2-1_2-Flange' => '2 1/2" Flange',
		'2-Flange' => '2" Flange',
		'2-1_2-Leg' => '2 1/2" Leg',
		'crc' =>	'CRC'
	);

	private function dirToArray($dir) { 
	   
	   $result = array(); 
	
	   $cdir = scandir($dir); 
	   foreach ($cdir as $key => $value) 
	   { 
		  if (!in_array($value,array(".",".."))) 
		  { 
			 if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) 
			 { 
				$result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value); 
			 } 
			 else 
			 { 
				$result[] = $value; 
				
			 } 
		  } 
	   } 
	   
	   return $result; 
	} 
	
	private function fix($input) {
		if (array_key_exists ( $input, $this->names )) {
			return $this->names[$input];
		}
		return str_replace('-',' ',$input);
	}
	
	public function __construct($path = '') {
		$array = $this->dirToArray($path);
		
		$count = 0;
		foreach ($array as $cat1name => $cat1item) {
			if (!is_array($cat1item)) continue;
			$this->tabs[] = $cat1name;
			$this->pages[$cat1name] = '<h2 class="csspagetitle">'.$this->fix($cat1name).'</h2>';
			foreach ($cat1item as $cat2name => $cat2item) {
				if (!is_array($cat2item)) continue;
				
				$this->pages[$cat1name] .= '<ul class="list">';
				$this->pages[$cat1name] .=  '<li>'.$this->fix($cat2name).'</li>';
				
				foreach ($cat2item as $cat3name => $cat3item) {
					if (!is_array($cat3item)) continue;
					$this->pages[$cat1name] .= '<ul class="sublist">';
					$this->pages[$cat1name] .=  '<li>'.$this->fix($cat3name).'</li>';
					
					$this->pages[$cat1name] .= '<ul class="files">';
					natsort($cat3item);
					foreach ($cat3item as $filename) {
						if (substr_compare( $filename, '.pdf', -strlen( '.pdf' ) ) === 0) {
							$prodname = str_replace('CSS-product-submittal-','',$filename);
							$prodname = str_replace('.pdf','',$prodname);
							$this->pages[$cat1name] .= '<li><input id="filecheck'.$count.'" type="checkbox" class="pdfcheck" name="docs[]" value="/submittals/'.$cat1name.'/'.$cat2name.'/'.$cat3name.'/'.$filename.'"><label for="filecheck'.$count.'">'.$prodname.'</label></li>';
							$count++;
						}
					}
					$this->pages[$cat1name] .= '</ul>';
				$this->pages[$cat1name] .= '</ul>';
				}
				$this->pages[$cat1name] .= '</ul>';
			}
		}
		return $count;
	}
}

class CreatePDF {

	private $tempname;
	public $meta_value;
	
	public function __construct($fields = array(), $template = '', $exportname = 'export') {
		
		//if (!is_array($_POST['docs']) || empty($_POST['docs'])) return false;
		$this->tempname = $this->generateRandomString(10).'.pdf';
		$path = __DIR__.'/export/';
		
		
		// get the template file
		$html = file_get_contents($template);
		
		// replace the wildcards
		foreach ($fields as $wildcard => $field) {
			
			$html = str_replace($wildcard,$field,$html);
	
		}


		// convert the template to PDF and save it to disk
		$html2pdf = new Html2Pdf('P', 'ANSI_A', 'en', false, 'UTF-8', array(25, 18, 25, 18));
		$html2pdf->writeHTML($html);
		$html2pdf->output($path.$this->tempname, 'F');
		
		// create new multipage pdf
		$outputpdf = new \Jurosh\PDFMerge\PDFMerger;
		
		// add as many pdfs as you want
		$outputpdf->addPDF($path.$this->tempname, 'all', 'vertical');
		foreach ($_POST['docs'] as $doc) {
			$outputpdf->addPDF($_SERVER['DOCUMENT_ROOT'].$doc, 'all', 'vertical');	
		}
		
		// checks to see if the file exists, adds a count if it does
		$count = null;
		while (file_exists($path.$exportname.$count.'.pdf')) {
			$count++;
		}
		
		// call merge, output format `file`
		$outputpdf->merge('file', $path.$exportname.$count.'.pdf');
		
		// delete the temp cover sheet
		unlink($path.$this->tempname);
		
		$user = wp_get_current_user(); 
		$this->meta_value = array(
			'project' => $_POST['project_name'],
			'date' => $fields['%%date%%'],
			'name' => $fields['%%company_name%%'],
			'phone' => $fields['%%company_phone%%'],
			'url' => $exportname.$count.'.pdf',
			'email' => $fields['%%company_email%%']
		);
		add_user_meta( $user->ID, 'submittals', $this->meta_value, false ); 

	}

	private function generateRandomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

}

class CSSSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        
        add_submenu_page( 
        	'tools.php', 
        	'MySubmittals Admin', 
        	'Submittals',
    		'manage_options', 
    		'css-submittals-admin', 
    		array( $this, 'create_admin_page' )
    	);
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'my_option_name' );
        ?>
        <div class="wrap">
            <h1>Contractor Steel Systems Settings</h1>
            <table>
            	<thead>
            		<tr>
            			<td>Company Name</td>
            			<td>Project Name</td>
            			<td>Project Date</td>
            			<td>User Name</td>
            			<td>Phone Number</td>
            			<td>Email Address</td>
            			<td>PDF</td>
            		<tr>
            	</thead>
            	<tbody>
            			
            <?php
			$users = get_users(array(
				'meta_key'     => 'submittals',
			)); 

			$path = __DIR__.'/export/';
			$fp = fopen($path.'submittals.csv', 'w');
			$userids = array();
			$header = array('Company Name','Project Name','Project Date','User Name','Phone','Email','PDF');
			fputcsv($fp, $header);
			foreach ($users as $user) {
				if (in_array($user->ID, $userids)) continue;
				$userids[] = $user->ID;
				$subs = get_user_meta($user->ID, 'submittals', false);
				
				foreach ($subs as $sub) { 
					$csv = array(
						'company_name' => (isset($sub['name']) ? $sub['name'] : ''),
						'project_name' => (isset($sub['project']) ? $sub['project'] : ''),
						'project_date' => (isset($sub['date']) ? $sub['date'] : ''),
						'user_name' =>(isset($user->user_login) ? $user->user_login : ''),
						'phone' => (isset($sub['phone']) ? $sub['phone'] : ''),
						'email' => (isset($sub['email']) ? $sub['email'] : ''),
						'pdf' => (isset($sub['url']) ? plugins_url( '/export/'.$sub['url'], __FILE__ ) : '')
					);
					fputcsv($fp, $csv);
					?>
					<tr>
            			<td><?php echo (isset($sub['name']) ? $sub['name'] : ''); ?></td>
            			<td><?php echo (isset($sub['project']) ? $sub['project'] : ''); ?></td>
            			<td><?php echo (isset($sub['date']) ? $sub['date'] : ''); ?></td>
            			<td><?php echo (isset($user->user_login) ? $user->user_login : ''); ?></td>
            			<td><?php echo (isset($sub['phone']) ? $sub['phone'] : ''); ?></td>
            			<td><?php echo (isset($sub['email']) ? $sub['email'] : ''); ?></td>
            			<td><?php echo (isset($sub['url']) ? '<a href="'.plugins_url( '/export/'.$sub['url'], __FILE__ ).'" target="_blank">'.$sub['url'].'</a>' : ''); ?></td>
            		</tr>
            <?php }
            } 
            fclose($fp);?>

				</tbody>
			</table>
			<a href="<?php echo plugins_url( '/export/submittals.csv', __FILE__ )?>" target="_blank">Download CSV</a>
			<?php /* ?>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'css_submittals_option_group' );
                do_settings_sections( 'css-submittals-admin' );
                submit_button();
            ?>
            </form>
            <?php */ ?>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'css_submittals_option_group', // Option group
            'css_submittals_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'My Custom Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'css-submittals-admin' // Page
        );  

        add_settings_field(
            'id_number', // ID
            'ID Number', // Title 
            array( $this, 'id_number_callback' ), // Callback
            'css-submittals-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'title', 
            'Title', 
            array( $this, 'title_callback' ), 
            'css-submittals-admin', 
            'setting_section_id'
        );      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['id_number'] ) )
            $new_input['id_number'] = absint( $input['id_number'] );

        if( isset( $input['title'] ) )
            $new_input['title'] = sanitize_text_field( $input['title'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function id_number_callback()
    {
        printf(
            '<input type="text" id="id_number" name="css_submittals_option_name[id_number]" value="%s" />',
            isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="text" id="title" name="css_submittals_option_name[title]" value="%s" />',
            isset( $this->options['title'] ) ? esc_attr( $this->options['title']) : ''
        );
    }
}

if( is_admin() )
    $my_settings_page = new CSSSettingsPage();


function cssbuild_shortcode_wp_enqueue_scripts() {
    wp_register_style( 'CSSBuild-style', plugins_url( '/css/submittals.css', __FILE__ ), array(), '1.0.0', 'all' );
    wp_register_script( 'CSSBuild-script', plugins_url( '/js/submittals.js', __FILE__ ), array('jquery'), '1.0.0' );
    wp_register_script( 'jquery-validateion', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.0/jquery.validate.min.js', array('jquery','CSSBuild-script'), '1.0.0' );
    wp_register_script( 'jquery-inputmask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.15/jquery.mask.min.js', array('jquery','CSSBuild-script'), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'cssbuild_shortcode_wp_enqueue_scripts' );

add_action( 'wp_ajax_css_create_submittal', 'css_create_submittal' );

function css_create_submittal() {
	
    if (!empty($_POST) && isset($_POST['date'])) { 
		
		require('vendor/autoload.php');
		
		$timestamp = strtotime($_POST['date']);
	 
		//Convert it to DD-MM-YYYY
		$dmy = date("m/d/Y", $timestamp);
	
		$fields = array(
			'%%addinfo%%'	=> $_POST['addinfo'],
			'%%arch_name%%'		=> $_POST['arch_name'],
			'%%arch_phone%%'=> $_POST['arch_phone'],
			'%%company_email%%'	=> $_POST['company_email'],
			'%%company_fax%%'	=> $_POST['company_fax'],
			'%%company_name%%'	=> $_POST['company_name'],
			'%%company_phone%%' => $_POST['company_phone'],
			'%%project%%'		=> $_POST['project_name'],
			'%%date%%'			=> $dmy,
			'%%gc_name%%'		=> $_POST['gc_name'],
			'%%gc_phone%%'		=> $_POST['gc_phone']		
		);
		$exportname = preg_replace("/[^0-9]/", '', $dmy).preg_replace("/[^A-Za-z0-9]/", '', $_POST['project_name']);
		$pdf = new CreatePDF($fields, plugins_url( 'covertemplate.html', __FILE__ ),$exportname);
		
		
		add_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );
		 		
		$headers[] = 'From: Contractor Steel Systems <sales@contractorsteelsystems.com>';
		//$headers[] = 'Cc: Sales <sales@contractorsteelsystems.com>';
		
		$message = '
		<html>
			<body>
				<h2>Thank you</h2>
				<p>Your submittal for <b>'.$_POST['project_name'].'</b> is attached.</p>
				<h4>Included Documents:</h4>
					<ul>';
					foreach ($_POST['docs'] as $doc) {
						$docname = explode('/',$doc);
						$prodname = str_replace('CSS-product-submittal-','',end($docname));
						$prodname = str_replace('.pdf','',$prodname);
						$message.= '<li>'.$prodname.'</li>';
					}
		$message .=	'</ul>
				<p>Contractor Steel Systems, Inc. is a one stop shop manufacturer that offers high quality steel framing products. CSS has the ability to provide a variety of materials to service customers all across the United States.</p>
				<p>Our goal is to produce top quality metal products for our customers and deliver those products in a timely, efficient, and cost effective way. The cornerstone of CSS is our commitment to maintain a reliable, dependable, an overall enjoyable customer experience. Repeat business from happy customers is what we strive to accomplish: customers who understand the dedication we put forth to keep our products and services at the highest level.</p>
				<p>We understand that our customers have countless options when deciding which steel manufacturer to partner with, so here at CSS we make sure every customers satisfaction is our primary purpose.</p>
			</body>
		</html>';
		 
		wp_mail( $pdf->meta_value['email'], 'Construction Submittal for '.$pdf->meta_value['project'], $message, $headers, array($path = __DIR__.'/export/'.$pdf->meta_value['url'] ));
		
		remove_filter( 'wp_mail_content_type', 'wpdocs_set_html_mail_content_type' );


		echo json_encode($pdf->meta_value);
 	} 
 	wp_die();
   
}

function wpdocs_set_html_mail_content_type() {
	return 'text/html';
}
add_action( 'wp_ajax_css_remove', 'css_remove' );

function css_remove() {
	$user = wp_get_current_user(); 
	$data = $_POST['data'];
	delete_user_meta( $user->ID, 'submittals', $data ); 
	wp_die();
}

add_shortcode( 'css_submittals', 'CSSBuildContent' );

function CSSBuildContent() {
	wp_enqueue_style( 'CSSBuild-style' );
	wp_enqueue_script( 'CSSBuild-script' );
	wp_localize_script( 'CSSBuild-script', 'css_ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    wp_enqueue_script( 'jquery-validateion' );
    wp_enqueue_script( 'jquery-inputmask' );
 	
 	$submittals = new CSSSubmittals($_SERVER['DOCUMENT_ROOT'].'/submittals');

	ob_start(); ?>
	<?php
		if(defined('REGISTRATION_ERROR')) {
			echo '<div class="csserror"><ul>';
			foreach(unserialize(REGISTRATION_ERROR) as $error) {
		  		echo "<li>{$error}</li>";
			}
			echo '</ul></div>';
		  // errors here, if any
		} elseif(defined('REGISTERED_A_USER')) {
		  	echo "<div class=\"cssnotice\">";
			echo 'Your account has been created, you may now log in with your username - '.REGISTERED_A_USER;
			echo "</div>";
		}
		if (isset($_GET['success']) && $_GET['success'] == 'true') {
			?>
			<div class="cssnotice">
				<h2>Success!</h2>
				<p>Your project submittal for <?php echo $_GET['project']; ?> has been emailed to <?php echo $_GET['email']; ?></p>
				You may also access your previous projects on this page at any time
			</div>
		<?php } ?>
	<div id="csswrap">
	<div class="leftcol">
		<ul class="tabs">
			<?php foreach ($submittals->tabs as $tab) {
				echo '<li class="tab" data-target="tab-'.$tab.'">'.str_replace('-',' ',$tab).'</li>';
			} 
			echo '<li class="tab" id="li-additionalinfo" data-target="tab-additionalinfo">Project Information</li>';
			?>
		</ul>	
	</div>
	
	<div class="pages">
		<?php if ($user = is_user_logged_in()) { ?>
		<form method="post" id="submittals">
		<?php } ?>
			<?php foreach ($submittals->pages as $name => $page) {
				echo '<div class="csspage" id="tab-'.$name.'">'.$page;
				echo '<div class="addprojectdetails">NEXT: Add Project Details</div>';
				echo '</div>';
			} ?>
			<div class="csspage" id="tab-additionalinfo">
			<?php
				if (is_user_logged_in()) { ?>
					<h2>Project Information</h2>
					<input type="text" name="project_name" placeholder= "Project Name" required /><br />
					<input type="date" name="date" placeholder="Submittal Date" required /><br />
					<p><h3>Company</h3>
					<input type="text" name="company_name" placeholder= "Company Name" required /><br />
					<input type="tel" name="company_phone" placeholder = "Phone Number" required /><br />
					<input type="email" name="company_email" placeholder = "Email Address" required /><br />
					<input type="tel" name="company_fax" placeholder = "Fax Number" /><br />
					</p>
					<p><h3>GC</h3>
					<input type="text" name="gc_name" placeholder = "GC Name" /><br />
					<input type="tel" name="gc_phone" placeholder = "Phone Number" /><br />
					</p>
					<p><h3>Architect</h3>
					<input type="text" name="arch_name" placeholder = "Architect Name" /><br />
					<input type="tel" name="arch_phone" placeholder = "Phone Number" /><br />
					</p>
					<p><h3>Additional Information</h3>
					<textarea name="addinfo" placeholder="Additional Information" cols="10"></textarea><br />
					</p>
					<input type="hidden" name="action" value = "css_create_submittal" />
					<div class="submit button" form="submittals" type="submit" >NEXT: Generate Submittal</div>
					<?php
				} else {
					?>
					<h2>You need to be logged in.</h2>
					<?php 
					global $wp;
					$current_url = home_url(add_query_arg(array(), $wp->request));
					wp_login_form(array('redirect' => $current_url)); ?>
					<h2>Or Register for a new account</h2>
					
					<form method="post" action="<?php echo add_query_arg('do', 'register', $current_url); ?>">
					  <label>
						Username:
						<input type="text" name="user" value=""/>
					  </label>
					<br />
					  <label>
						Email:
					   <input type="text" name="email" value="" />
					  </label>
					<br />
					  <label>
						Password:
					   <input type="password" name="password" value="" />
					  </label>
					<br />
					  <label>
						Confirm:
					   <input type="password" name="confirm" value="" />
					  </label>
					
					  <input type="submit" value="register" />
					</form>
				<?php } ?>
			</div>
			
		<?php if (is_user_logged_in()) { ?>
		</form>
		<?php } ?>
	</div>
	<div class="leftcol">
		<h2>Selected Items</h2>
		<ul class="selectlist">
			<li>No items selected</li>
		</ul>
	</div>
	<div class="leftcol">
		<h2>Saved Projects:</h2>
		<?php if (is_user_logged_in()) { 
			$user = wp_get_current_user(); 
			$usersubs = get_user_meta($user->ID, 'submittals', false);
				if (is_array($usersubs) && !empty($usersubs)) { ?>
					<ul class="projectlist">
					<?php foreach ($usersubs as $sub) { ?>
						<li><a class="project" href="<?php echo plugins_url( '/export/'.$sub['url'], __FILE__ ); ?>" target="_blank"><?php echo $sub['project']; ?></a>
							<br /><span class="date"><?php echo $sub['date']; ?></span> <a class="remove" data-project = "<?php echo htmlentities(json_encode($sub)); ?>" >Delete</a>
						</li>
					<?php } ?>
					</ul>
				<?php } else { ?>
					You don't have any projects, start one now!
				<?php } ?>
		<?php } else { ?>
			You need to be logged in to view your previous project submittals.
		<?php } ?>
			
	</div>
	</div>
	<?php
	$output = ob_get_contents();
	ob_end_clean();
	
	return $output;
}

add_action('template_redirect', 'register_a_user');

function register_a_user(){
  if(isset($_GET['do']) && $_GET['do'] == 'register'):
    $errors = array();
    if(empty($_POST['user']) || empty($_POST['email'])) $errors[] = 'provide a user and email';
	if(empty($_POST['password']) || empty($_POST['confirm'])) $errors[] = 'Provide a password and confirmation';
    
    $user_login = esc_attr($_POST['user']);
    $user_email = esc_attr($_POST['email']);
    $pass = esc_attr($_POST['password']);
    $confirm = esc_attr($_POST['confirm']);
    
    if($_POST['password'] !== $_POST['confirm']) $errors[] = 'Password and confirmation do not match';

    $sanitized_user_login = sanitize_user($user_login);
    $user_email = apply_filters('user_registration_email', $user_email);

    if(!is_email($user_email)) $errors[] = 'invalid e-mail';
    elseif(email_exists($user_email)) $errors[] = 'this email is already registered';

    if(empty($sanitized_user_login) || !validate_username($user_login)) $errors[] = 'invalid user name';
    elseif(username_exists($sanitized_user_login)) $errors[] = 'user name already exists';

    if(empty($errors)):
      $user_pass = $pass;
      $user_id = wp_create_user($sanitized_user_login, $user_pass, $user_email);

      if(!$user_id):
        $errors[] = 'registration failed...';
      else:
        
      endif;
    endif;

    if(!empty($errors)) define('REGISTRATION_ERROR', serialize($errors));
    else define('REGISTERED_A_USER', $sanitized_user_login);
  endif;
}
