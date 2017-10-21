<?php

namespace WCInvoicePdf\Menu;

use WCInvoicePdf\Model\InvoiceList as InvoiceList;

class InvoiceMenu {
    public function __construct(){
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    public function admin_menu(){
        add_menu_page( 'WC-' . __('Invoices', 'wc-invoice-pdf'), 'WC-' . __('Invoices', 'wc-invoice-pdf'), 'null', 'wcinvoicepdf_menu',  null, WCINVOICEPDF_PLUGIN_URL.'img/ispconfig.png', 3);
        add_submenu_page('wcinvoicepdf_menu', __('Invoices', 'wc-invoice-pdf'), __('Invoices', 'wc-invoice-pdf'), 'edit_themes', 'wcinvoicepdf_invoices',  [$this, 'DisplayInvoices'] );
        add_submenu_page('wcinvoicepdf_menu', __('Settings'), __('Settings'), 'edit_themes', 'wcinvoicepdf_settings',  [$this, 'DisplaySettings'] );
    }

    public function DisplayInvoices(){
        $invList = new InvoiceList();
        
        $a = $invList->current_action();
        $invList->prepare_items();
        ?>
        <div class='wrap'>
            <h1><?php _e('Invoices', 'wc-invoice-pdf') ?></h1>
            <h2></h2>
            <form action="" method="GET">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                <input type="hidden" name="action" value="filter" />
                <label class="post-attributes-label" for="user_login">Filter Customer:</label>
                <select name="id" style="min-width: 200px">
                    <option value="">[any]</option>
                <?php  
                $users = get_users(['role' => 'customer']);
                foreach ($users as $u) {
                    $company = get_user_meta($u->ID, 'billing_company', true);
                    $selected = (isset($_GET['id']) && $u->ID == $_GET['id'])?'selected':'';
                    echo '<option value="'.$u->ID.'" '.$selected.'>'. $company . ' (' .$u->user_login.')</option>';
                }
                ?>
                </select>
                <input type="checkbox" id="recur_only" name="recur_only" value="1" <?php echo (!empty($_GET['recur_only'])?'checked':'') ?> /> <label for="recur_only">Recurring only</label>
                <input type="submit" value="filter">
                <input type="button" value="Reset" onclick="document.location.href='?page=<?php echo $_REQUEST['page'] ?>'">
            </form>
            <?php $invList->display(); ?>
        </div>
        <?php
    }

    public function DisplaySettings(){
        echo "<h3>No settings yet</h3>";
    }
}