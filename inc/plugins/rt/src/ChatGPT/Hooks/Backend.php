<?php
/**
 * RT ChatGPT Assistant
 *
 * RT ChatGPT utilizes OpenAI API to generate responses and do tasks.
 *
 * @package rt_chatgpt
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

namespace rt\ChatGPT\Hooks;

final class Backend
{

    /**
     * Hook: admin_load
     *
     * @return void
     */
    public function admin_load(): void
    {
        global $db, $mybb, $lang, $run_module, $action_file, $page, $sub_tabs, $form, $config;

        if ($run_module === 'tools' && $action_file === \rt\ChatGPT\Core::$PLUGIN_DETAILS['prefix'])
        {
            $table = new \Table();
            $table_prefix = TABLE_PREFIX;
            $prefix = \rt\ChatGPT\Core::$PLUGIN_DETAILS['prefix'];
            $lang->load($prefix);

            $page->add_breadcrumb_item($lang->{$prefix . '_menu'}, "index.php?module=tools-{$prefix}");

            $page_url = "index.php?module={$run_module}-{$action_file}";

            $sub_tabs = [];

            $allowed_actions =
            $tabs = [
                'logs'
            ];

            foreach ($tabs as $row)
            {
                $sub_tabs[$row] = [
                    'link' => $page_url . '&amp;action=' . $row,
                    'title' => $lang->{$prefix .'_tab_' . $row},
                    'description' => $lang->{$prefix . '_tab_' . $row . '_desc'},
                ];
            }

            if (!$mybb->input['action'] || $mybb->input['action'] === 'logs')
            {
                $page->output_header($lang->{$prefix . '_menu'} . ' - ' . $lang->{$prefix .'_tab_' . 'logs'});
                $page->output_nav_tabs($sub_tabs, 'logs');

                if ($mybb->request_method === 'post')
                {
                    if (!empty($mybb->get_input('delete_all')))
                    {
                        $db->delete_query('rt_chatgpt_logs');
                        $num_deleted = $db->affected_rows();

                        // Log admin action
                        log_admin_action($num_deleted);

                        flash_message($lang->rt_chatgpt_logs_all_deleted, 'success');
                        admin_redirect("index.php?module=tools-rt_chatgpt&amp;action=logs");
                    }

                    if (!empty($mybb->get_input('log', \MyBB::INPUT_ARRAY)))
                    {
                        $log_ids = implode(",", array_map("intval", $mybb->get_input('log', \MyBB::INPUT_ARRAY)));

                        if($log_ids)
                        {
                            $db->delete_query("rt_chatgpt_logs", "id IN ({$log_ids})");
                            $num_deleted = $db->affected_rows();

                            // Log admin action
                            log_admin_action($num_deleted);
                        }
                        flash_message($lang->rt_chatgpt_logs_selected_deleted, 'success');
                        admin_redirect("index.php?module=tools-rt_chatgpt&amp;action=logs");
                    }

                }

                $query = $db->write_query(<<<SQL
                SELECT
                    COUNT(*) as logs
                FROM
                    {$table_prefix}rt_chatgpt_logs
                SQL);

                $total_rows = $db->fetch_field($query, "logs");

                $per_page = 20;
                $pagenum = $mybb->get_input('page', \MyBB::INPUT_INT);

                if($pagenum)
                {
                    $start = ($pagenum - 1) * $per_page;
                    $pages = ceil($total_rows / $per_page);
                    if($pagenum > $pages)
                    {
                        $start = 0;
                        $pagenum = 1;
                    }
                }
                else
                {
                    $start = 0;
                    $pagenum = 1;
                }

                $query = $db->write_query(<<<SQL
				SELECT
				   *
				FROM
					{$table_prefix}rt_chatgpt_logs
				ORDER BY
					dateline DESC
				LIMIT
					{$start}, {$per_page}
				SQL);

                $form = new \Form("index.php?module=tools-{$prefix}&amp;action=logs", "post", "logs");
                $table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
                $table->construct_header($lang->{$prefix . '_logs_message'});
                $table->construct_header($lang->{$prefix . '_logs_oid'});
                $table->construct_header($lang->{$prefix . '_logs_model'});
                $table->construct_header($lang->{$prefix . '_logs_used_tokens'});
                $table->construct_header($lang->{$prefix . '_logs_action'});
                $table->construct_header($lang->{$prefix . '_logs_status'});
                $table->construct_header($lang->{$prefix . '_logs_dateline'}, [
                    'class' => 'align_center'
                ]);

                foreach ($query as $row)
                {
                    switch ($row['status'])
                    {
                        case 1:
                            $row['status'] = "<img src=\"{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/tick.png\" alt=\"\">";
                            break;
                        default:
                            $row['status'] = "<img src=\"{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/cross.png\" alt=\"\">";
                    }

                    $row['dateline'] = my_date('relative', $row['dateline']);
                    $row['message'] = htmlspecialchars_uni($row['message']);
                    $row['action'] = htmlspecialchars_uni($row['action']);
                    $row['oid'] = isset($row['oid']) ? htmlspecialchars_uni($row['oid']) : $lang->na;
                    $row['model'] = isset($row['model']) ? htmlspecialchars_uni($row['model']) : $lang->na;
                    $row['used_tokens'] = isset($row['used_tokens']) ? (int) $row['used_tokens'] : $lang->na;

                    $table->construct_cell($form->generate_check_box("log[{$row['id']}]", $row['id'], ''));

                    $table->construct_cell($row['message'], [
                        'class' =>  'align_left',
                    ]);

                    $table->construct_cell($row['oid'], [
                        'class' => 'align_left'
                    ]);

                    $table->construct_cell($row['model'], [
                        'class' => 'align_left'
                    ]);

                    $table->construct_cell($row['used_tokens'], [
                        'class' => 'align_left'
                    ]);

                    $table->construct_cell($row['action'], [
                        'class' => 'align_left'
                    ]);

                    $table->construct_cell($row['status'], [
                        'class' =>  'align_center',
                    ]);

                    $table->construct_cell($row['dateline'], [
                        'class' =>  'align_center',
                    ]);
                    $table->construct_row();
                }

                if($table->num_rows() === 0)
                {
                    $table->construct_cell($lang->rt_chatgpt_logs_notfound, ['colspan' => '5']);
                    $table->construct_row();
                }

                $table->output($lang->{$prefix . '_logs_list'});

                $buttons[] = $form->generate_submit_button($lang->delete_selected, array('onclick' => "return confirm('{$lang->rt_chatgpt_logs_delete_selected}');"));
                $buttons[] = $form->generate_submit_button($lang->delete_all, array('name' => 'delete_all', 'onclick' => "return confirm('{$lang->rt_chatgpt_logs_delete_all}');"));
                $form->output_submit_wrapper($buttons);
                $form->end();

                echo draw_admin_pagination($pagenum, $per_page, $total_rows, "index.php?module=tools-{$prefix}&amp;action=logs");

                $page->output_footer();
            }

            try
            {
                if (!in_array($mybb->get_input('action'), $allowed_actions))
                {
                    throw new \Exception('Not allowed!');
                }
            }
            catch (\Exception $e)
            {
                flash_message($e->getMessage(), 'error');
                admin_redirect("index.php?module=tools-{$prefix}");
            }
        }
    }

    /**
     * Hook: admin_tools_action_handler
     *
     * @param array $actions
     * @return void
     */
    public function admin_tools_action_handler(array &$actions): void
    {
        $prefix = \rt\ChatGPT\Core::$PLUGIN_DETAILS['prefix'];

        $actions[$prefix] = [
            'active' => $prefix,
            'file' => $prefix,
        ];
    }

    /**
     * Hook: admin_tools_menu
     *
     * @param array $sub_menu
     * @return void
     */
    public function admin_tools_menu(array &$sub_menu): void
    {
        global $lang;

        $prefix = \rt\ChatGPT\Core::$PLUGIN_DETAILS['prefix'];
        $lang->load($prefix);

        $sub_menu[] = [
            'id' => $prefix,
            'title' => $lang->rt_chatgpt_menu,
            'link' => 'index.php?module=tools-' . $prefix,
        ];
    }
}