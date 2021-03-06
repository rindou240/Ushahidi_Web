<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Comments Controller.
 * This controller will take care of viewing and editing comments in the Admin section.
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     Admin Reports Controller  
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class Comments_Controller extends Admin_Controller
{
    function __construct()
    {
        parent::__construct();
    
        $this->template->this_page = 'reports';
        
        // If user doesn't have access, redirect to dashboard
        if ( ! admin::permissions($this->user, "reports_comments"))
        {
            url::redirect(url::site().'admin/dashboard');
        }
    }
    
    
    /**
    * Lists the reports.
    * @param int $page
    */
    function index($page = 1)
    {
        $this->template->content = new View('admin/comments');
        $this->template->content->title = Kohana::lang('ui_admin.comments');

		$filter = '';
        
		$r_from = "";
		if( isset($_GET['from']) )
		{
			$r_from = $this->input->xss_clean($_GET['from']);
		}
		$r_to = "";
		if( isset($_GET['to']) )
		{
			$r_to = $this->input->xss_clean($_GET['to']);
		}

		$filter_range = "";
		if( isset($r_from) && empty($r_to) )
		{
			$filter_range = "comment_date between \"".date("Y-m-d",strtotime($r_from))." 00:00:00\" and \"".date("Y-m-d")." 23:59:00\"";
		} elseif( isset($r_from) && isset($r_to) )
		{
			$filter_range = "comment_date between \"".date("Y-m-d",strtotime($r_from))." 00:00:00\" and \"".date("Y-m-d",strtotime($r_to))." 23:59:00\"";
		} elseif( empty($r_from) && isset($r_to) )
		{
			$filter_range = "comment_date between \"".date("Y-m-d",1)." 00:00:00\" and \"".date("Y-m-d",strtotime($r_to))." 23:59:00\"";
		}

		$filter_status = "";
        if (!empty($_GET['status']))
        {
            $status = $_GET['status'];
            
            if (strtolower($status) == 'a')
            {
                $filter_status = 'comment_active = 1 AND comment_spam = 0';
            }
            elseif (strtolower($status) == 'p')
            {
                $filter_status = 'comment_active = 0 AND comment_spam = 0';
            }
            elseif (strtolower($status) == 's')
            {
                $filter_status = 'comment_spam = 1';
            }
            else
            {
                $status = "0";
                $filter_status = 'comment_spam = 0';
            }
        }
        else
        {
            $status = "0";
            $filter_status = 'comment_spam = 0';
        }

		// filter string build.
        $filter = $filter_status;
        $filter .= ((!empty($filter))? ((!empty($filter_range))? (" AND ".$filter_range):""):$filter_range);
		if (empty($filter))
		{
            $filter = 'comment_spam = 0';
		}
        
        // check, has the form been submitted?
        $form_error = FALSE;
        $form_saved = FALSE;
        $form_action = "";
        if ($_POST)
        {
            $post = Validation::factory($_POST);
            
             //  Add some filters
            $post->pre_filter('trim', TRUE);

            // Add some rules, the input field, followed by a list of checks, carried out in order
            $post->add_rules('action','required', 'alpha', 'length[1,1]');
            $post->add_rules('comment_id.*','required','numeric');
            
            if ($post->validate())
            {
                if ($post->action == 'a')       
                { // Approve Action
                    foreach($post->comment_id as $item)
                    {
                        $update = new Comment_Model($item);
                        if ($update->loaded == true) {
                            $update->comment_active = '1';
                            $update->comment_spam = '0';
                            $update->save();
                        }
                    }
                    $form_action = strtoupper(Kohana::lang('ui_admin.approved'));
                }
                elseif ($post->action == 'u')   
                { // Unapprove Action
                    foreach($post->comment_id as $item)
                    {
                        $update = new Comment_Model($item);
                        if ($update->loaded == true) {
                            $update->comment_active = '0';
                            $update->save();
                        }
                    }
                    $form_action = strtoupper(Kohana::lang('ui_admin.unapproved'));
                }
                elseif ($post->action == 's')   
                { // Spam Action
                    foreach($post->comment_id as $item)
                    {
                        $update = new Comment_Model($item);
                        if ($update->loaded == true) {
                            $update->comment_spam = '1';
                            $update->comment_active = '0';
                            $update->save();
                        }
                    }
                    $form_action = strtoupper(Kohana::lang('ui_admin.marked_as_spam'));
                }
                elseif ($post->action == 'n')   
                { // Spam Action
                    foreach($post->comment_id as $item)
                    {
                        $update = new Comment_Model($item);
                        if ($update->loaded == true) {
                            $update->comment_spam = '0';
                            $update->comment_active = '1';
                            $update->save();
                        }
                    }
                    $form_action = strtoupper(Kohana::lang('ui_admin.marked_as_not_spam'));
                }
                elseif ($post->action == 'd')   // Delete Action
                {
                    foreach($post->comment_id as $item)
                    {
                        $update = new Comment_Model($item);
                        if ($update->loaded == true)
                        {
                            $update->delete();
                        }                   
                    }
                    $form_action = Kohana::lang('ui_admin.deleted');
                }
                elseif ($post->action == 'x')   // Delete All Spam Action
                {
                    ORM::factory('comment')->where('comment_spam','1')->delete_all();
                    $form_action = Kohana::lang('ui_admin.deleted');
                }
                $form_saved = TRUE;
            }
            else
            {
                $form_error = TRUE;
            }
            
        }
        
		$order = 0;
		$order_string = "desc";
		if( isset($_GET['order']) )
		{
			$order = intval($_GET['order']);
			if ( $order == 0 )
			{
				$order_string = "desc";
			} elseif ( $order == 1 ) {
				$order_string = "asc";
			} else {
				$order = 0;
				$order_string = "desc";
			}
		}
        
        // Pagination
        $pagination = new Pagination(array(
            'query_string'    => 'page',
            'items_per_page' => (int) Kohana::config('settings.items_per_page_admin'),
            'total_items'    => ORM::factory('comment')->where($filter)->count_all()
        ));

        $comments = ORM::factory('comment')->where($filter)->orderby('comment_date', $order_string)->find_all((int) Kohana::config('settings.items_per_page_admin'), $pagination->sql_offset);
        
		$this->template->content->from = $r_from;
		$this->template->content->to = $r_to;
        $this->template->content->order = $order;
        $this->template->content->comments = $comments;
        $this->template->content->pagination = $pagination;
        $this->template->content->form_error = $form_error;
        $this->template->content->form_saved = $form_saved;
        $this->template->content->form_action = $form_action;
        
        // Total Reports
        $this->template->content->total_items = $pagination->total_items;
        
        // Status Tab
        $this->template->content->status = $status;
        
        // Javascript Header
        $this->template->js = new View('admin/comments_js');        
    }
    
}
