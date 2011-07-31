<?php
/*
Plugin Name: Atnd for Wordpress
Description: ATNDからイベント開催情報を表示するプラグイン
Plugin URI: http://ecogrammer.manno.jp/atnd-for-wordpress/
Version: 0.3
Author URI: http://ecogrammer.manno.jp/
Author: Junji Manno
*/

define('MAGPIE_CACHE_ON', 1);
define('MAGPIE_CACHE_AGE', 180);
define('MAGPIE_INPUT_ENCODING', 'UTF-8');
define('MAGPIE_OUTPUT_ENCODING', 'UTF-8');

$atnd_options['widget_fields']['title'] = array('label'=>'タイトル:', 'type'=>'text', 'default'=>'fukuoka');
$atnd_options['widget_fields']['keyword'] = array('label'=>'キーワード（AND）カンマ区切り:', 'type'=>'text', 'default'=>'');
$atnd_options['widget_fields']['keyword_or'] = array('label'=>'キーワード（OR）カンマ区切り:', 'type'=>'text', 'default'=>'');
$atnd_options['widget_fields']['nickname'] = array('label'=>'参加者のニックネーム カンマ区切り:', 'type'=>'text', 'default'=>'');
$atnd_options['widget_fields']['owner_nickname'] = array('label'=>'主催者のニックネーム カンマ区切り:', 'type'=>'text', 'default'=>'');
$atnd_options['widget_fields']['count'] = array('label'=>'表示件数:', 'type'=>'text', 'default'=>'5');
$atnd_options['widget_fields']['description'] = array('label'=>'概要のみ表示:', 'type'=>'checkbox', 'default'=>true);
$atnd_options['widget_fields']['author_name'] = array('label'=>'主催者を表示:', 'type'=>'checkbox', 'default'=>true);
$atnd_options['widget_fields']['update'] = array('label'=>'更新日時を表示:', 'type'=>'checkbox', 'default'=>true);
$atnd_options['widget_fields']['linked'] = array('label'=>'ATNDリンク:', 'type'=>'text', 'default'=>'>>');
$atnd_options['widget_fields']['encode_utf8'] = array('label'=>'UTF8 Encode:', 'type'=>'checkbox', 'default'=>false);

$atnd_options['prefix'] = 'atnd';

// atnd ログ表示
function atnd_messages(
    $keyword = '', 
    $keyword_or = '', 
    $nickname = '', 
    $owner_nickname = '',
    $count = 1, 
    $list = false, 
    $author_name = true, 
    $update = true, 
    $linked  = '#', 
    $encode_utf8 = false,
    $description = true
){
    global $atnd_options;
    include_once(ABSPATH . WPINC . '/rss.php');

    $url = 
        'http://api.atnd.org/events/'.
        '?keyword='.$keyword.
        '&keyword_or='.$keyword_or.
        '&nickname='.$nickname.
        '&owner_nickname='.$owner_nickname.
        '&count='.$count.
        '&format=atom';

    $messages = fetch_rss($url);

    if ($list) echo '<ul class="atnd">';

    if ($keyword == '' && $keyword_or == '' && $nickname == '' && $owner_nickname == '') {
        if ($list) echo '<li>';
        echo 'RSS not configured';
        if ($list) echo '</li>';
    } else {
        if ( empty($messages->items) ) {
            if ($list) echo '<li>';
            echo 'No public atnd messages.';
            if ($list) echo '</li>';
        } else {
            $i = 0;
            foreach ( $messages->items as $message ) {

                //var_dump($message);

                // タイトル title
                $title = $message['title'];
                if($encode_utf8) $title = utf8_encode($title);

                // メッセージ atom_content
                // 概要のみ表示
                if($description) {
                    $token_position = strpos($message['atom_content'], '<br/><br/>');
                    $msg = substr($message['atom_content'], 0,$token_position);
                // 全て表示
                } else {
                    $msg = $message['atom_content'];
                }
                if($encode_utf8) $msg = utf8_encode($msg);

                // 主催者 auther_name
                if ($author_name) {
                    $name = "<br />主催者：".$message['author_name'];
                    if($encode_utf8) {
                        $msg .= utf8_encode($name);
                    } else {
                        $msg .= $name;
                    }
                }

                // リンク 
                $link = $message['link'];

                if ($list)
                    echo '<li class="atnd-item">';
                elseif ($count != 1) 
                    echo '<p class="atnd-message">';

                // メッセージリンク
                if ($linked != '' || $linked != false) {
                    if($linked == 'all') {
                        // Puts a link to the status of each tweet 
                        $msg = '<a href="'.$link.'" class="atnd-link">'.$title.'</a><br />'.$msg;
                    } else {
                        // Puts a link to the status of each tweet
                        $msg = $title.'<br />'.$msg . '<br /><a href="'.$link.'" class="atnd-link">'.$linked.'</a>';
                    }
                }

            echo $msg;

            // atnd登録日
            if($update) {
                $time = strtotime($message['updated']);
                if ( ( abs( time() - $time) ) < 86400 )
                    $h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
                else
                    $h_time = date(__('Y/m/d'), $time);
                    echo sprintf( __('%s', 'atnd-for-wordpress'),' <span class="atnd-timestamp"><abbr title="' . date(__('Y/m/d H:i:s'), $time) . '">' . $h_time . '</abbr></span>' );
                }
                if ($list) echo '</li>'; elseif ($count != 1) echo '</p>';
                
                $i++;
                if ( $i >= $count ) break;
            }
        }
    }

    if ($list) echo '</ul>';
}

// atndウィジット設定情報
function widget_atnd_init()
{
    if ( !function_exists('register_sidebar_widget') ){
        return;
    }

	// widget_atnd からオプションの取得
    $check_options = get_option('widget_atnd');

    if ($check_options['number']=='') {
        $check_options['number'] = 1;
        // widget_atnd からオプションを更新
        update_option('widget_atnd', $check_options);
    }

    // atndウィジット設定
    function widget_atnd($args, $number = 1)
    {
        global $atnd_options;
        
        extract($args);
        
        include_once(ABSPATH . WPINC . '/rss.php');
        $options = get_option('widget_atnd');
        
        $item = $options[$number];
        
        foreach($atnd_options['widget_fields'] as $key => $field) {
            if (! isset($item[$key])) {
                $item[$key] = $field['default'];
            }
        }

        echo $before_widget . $before_title . 
            '<a href="http://api.atnd.org/events/?keyword=' . $item['keyword'] . 
            '&keyword_or='. $item['keyword_or'] . 
            '&nickname=' . $item['nickname'] . 
            '&owner_nickname=' . $item['owner_nickname'] . 
            '&format=atom" class="atnd_title_link">'. $item['title'] . '</a>' . 
            $after_title;

        //echo "<pre>";
        //var_dump ($item);
        //echo "</pre>";

        atnd_messages(
            $item['keyword'], 
            $item['keyword_or'], 
            $item['nickname'], 
            $item['owner_nickname'],
            $item['count'], 
            true,        
            $item['author_name'], 
            $item['update'], 
            $item['linked'], 
            $item['encode_utf8'],
            $item['description']
        );

        echo $after_widget;

    }

    // atnd ウィジットコントロール画面
    function widget_atnd_control($number)
    {
        global $atnd_options;

        $options = get_option('widget_atnd');
        if ( isset($_POST['atnd-submit']) ) {

            foreach($atnd_options['widget_fields'] as $key => $field) {
                $options[$number][$key] = $field['default'];
                $field_name = sprintf('%s_%s_%s', $atnd_options['prefix'], $key, $number);

                if ($field['type'] == 'text') {
                    $options[$number][$key] = strip_tags(stripslashes($_POST[$field_name]));
                } elseif ($field['type'] == 'checkbox') {
                    $options[$number][$key] = isset($_POST[$field_name]);
                }
            }

            update_option('widget_atnd', $options);
        }

        foreach($atnd_options['widget_fields'] as $key => $field) {

            $field_name = sprintf('%s_%s_%s', $atnd_options['prefix'], $key, $number);
            $field_checked = '';
            if ($field['type'] == 'text') {
                $field_value = htmlspecialchars($options[$number][$key], ENT_QUOTES);
            } elseif ($field['type'] == 'checkbox') {
                $field_value = 1;
                if (! empty($options[$number][$key])) {
                    $field_checked = 'checked="checked"';
                }
            }

            printf('
                 <p style="text-align:right;" class="atnd_field"><label for="%s">%s  
                     <input id="%s" name="%s" type="%s" value="%s" class="%s" %s /></label></p>',
                 $field_name, 
                 __($field['label']), 
                 $field_name, 
                 $field_name, 
                 $field['type'], 
                 $field_value, 
                 $field['type'], 
                 $field_checked
            );
        }

        echo '<input type="hidden" id="atnd-submit" name="atnd-submit" value="1" />';

    }

    // atnd ウィジットセットアップ
    function widget_atnd_setup()
    {
        $options = $newoptions = get_option('widget_atnd');

        if ( isset($_POST['atnd-number-submit']) ) {
            $number = (int) $_POST['atnd-number'];
            $newoptions['number'] = $number;
        }
        if ( $options != $newoptions ) {
            update_option('widget_atnd', $newoptions);
            widget_atnd_register();
        }
    }

    // atnd ウィジット登録
    function widget_atnd_register()
    {
        $options = get_option('widget_atnd');
        $dims = array('width' => 350, 'height' => 300);
        $class = array('classname' => 'widget_atnd');

        for ($i = 1; $i <= 9; $i++) {
            $name = sprintf(__('atnd #%d'), $i);
            $id = "atnd-$i"; // Never never never translate an id
            wp_register_sidebar_widget($id, $name, $i <= $options['number'] ? 'widget_atnd' : /* 未登録 */ '', $class, $i);
            wp_register_widget_control($id, $name, $i <= $options['number'] ? 'widget_atnd_control' : /* 未登録 */ '', $dims, $i);
        }
        
        add_action('sidebar_admin_setup', 'widget_atnd_setup');

    }

    widget_atnd_register();

}


// widget_atnd_init をウィジットに登録
add_action('widgets_init', 'widget_atnd_init');
?>