<?php
/**
 * DIMP Base Class - provides dynamic view functions.
 *
 * Copyright 2005-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class DIMP
{
    /**
     * Output a dimp-style action (menubar) link.
     *
     * @param array $params  A list of parameters.
     * <pre>
     * 'app' - The application to load the icon from.
     * 'class' - The CSS classname to use for the link.
     * 'icon' - The icon CSS classname.
     * 'id' - The DOM ID of the link.
     * 'title' - The title string.
     * 'tooltip' - Tooltip text to use.
     * </pre>
     *
     * @return string  An HTML link to $url.
     */
    static public function actionButton($params = array())
    {
        $tooltip = (empty($params['tooltip'])) ? '' : $params['tooltip'];

        if (empty($params['title'])) {
            $old_error = error_reporting(0);
            $tooltip = nl2br(htmlspecialchars($tooltip, ENT_QUOTES, Horde_Nls::getCharset()));
            $title = $ak = '';
        } else {
            $title = $params['title'];
            $ak = Horde::getAccessKey($title);
        }

        return Horde::link('', $tooltip,
                           empty($params['class']) ? '' : $params['class'],
                           '', '', '', $ak,
                           empty($params['id']) ? array() : array('id' => $params['id']),
                           !empty($title))
            . (!empty($params['icon'])
                  ? '<span class="iconImg dimpaction' . $params['icon'] . '"></span>'
                  : '')
            . $title . '</a>';
    }

    /**
     * Output everything up to but not including the <body> tag.
     *
     * @param string $title   The title of the page.
     * @param array $scripts  Any additional scripts that need to be loaded.
     *                        Each entry contains the three elements necessary
     *                        for a Horde::addScriptFile() call.
     */
    static public function header($title, $scripts = array())
    {
        // Don't autoload any javascript files.
        Horde::disableAutoloadHordeJS();

        // Need to include script files before we start output
        Horde::addScriptFile('prototype.js', 'horde', true);
        Horde::addScriptFile('effects.js', 'horde', true);

        // ContextSensitive must be loaded before DimpCore.
        while (list($key, $val) = each($scripts)) {
            if (($val[0] == 'ContextSensitive.js') &&
                ($val[1] == 'imp')) {
                Horde::addScriptFile($val[0], $val[1], $val[2]);
                unset($scripts[$key]);
                break;
            }
        }
        Horde::addScriptFile('DimpCore.js', 'imp', true);
        Horde::addScriptFile('Growler.js', 'horde', true);

        // Add other scripts now
        foreach ($scripts as $val) {
            call_user_func_array(array('Horde', 'addScriptFile'), $val);
        }

        $page_title = $GLOBALS['registry']->get('name');
        if (!empty($title)) {
            $page_title .= ' :: ' . $title;
        }

        if (isset($GLOBALS['language'])) {
            header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
            header('Vary: Accept-Language');
        }

        echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"DTD/xhtml1-transitional.dtd\">\n" .
             (!empty($GLOBALS['language']) ? '<html lang="' . strtr($GLOBALS['language'], '_', '-') . '"' : '<html') . ">\n".
             "<head>\n";

        echo '<title>' . htmlspecialchars($page_title) . "</title>\n" .
             '<link href="' . $GLOBALS['registry']->getImageDir() . "/favicon.ico\" rel=\"SHORTCUT ICON\" />\n";
        include IMP_BASE . '/templates/javascript_defs_dimp.php';

        IMP::includeStylesheetFiles('dimp');

        echo "</head>\n";

        // Send what we have currently output so the browser can start
        // loading CSS/JS. See:
        // http://developer.yahoo.com/performance/rules.html#flush
        flush();
    }

    /**
     * Return an appended IMP folder string
     */
    static private function _appendedFolderPref($folder)
    {
        return IMP::folderPref($folder, true);
    }

    /**
     * Return the javascript code necessary to display notification popups.
     *
     * @return string  The notification JS code.
     */
    static public function notify()
    {
        $GLOBALS['notification']->notify(array('listeners' => 'status'));
        $msgs = $GLOBALS['imp_notify']->getStack();

        return count($msgs)
            ? 'DimpCore.showNotifications(' . Horde_Serialize::serialize($msgs, Horde_Serialize::JSON) . ')'
            : '';
    }

    /**
     * Formats the response to send to javascript code when dealing with
     * folder operations.
     *
     * @param IMP_Tree $imptree  An IMP_Tree object.
     * @param array $changes     An array with three sub arrays - to be used
     *                           instead of the return from
     *                           $imptree->eltDiff():
     *                           'a' - a list of folders/objects to add
     *                           'c' - a list of changed folders
     *                           'd' - a list of folders to delete
     *
     * @return array  The object used by the JS code to update the folder tree.
     */
    static public function getFolderResponse($imptree, $changes = null)
    {
        if (is_null($changes)) {
            $changes = $imptree->eltDiff();
        }
        if (empty($changes)) {
            return false;
        }

        $result = array();

        if (!empty($changes['a'])) {
            $result['a'] = array();
            foreach ($changes['a'] as $val) {
                $result['a'][] = self::_createFolderElt(is_array($val) ? $val : $imptree->element($val));
            }
        }

        if (!empty($changes['c'])) {
            $result['c'] = array();
            foreach ($changes['c'] as $val) {
                // Skip the base element, since any change there won't ever be
                // updated on-screen.
                if ($val != IMP_Imap_Tree::BASE_ELT) {
                    $result['c'][] = self::_createFolderElt($imptree->element($val));
                }
            }
        }

        if (!empty($changes['d'])) {
            $result['d'] = array_map('rawurlencode', array_reverse($changes['d']));
        }

        return $result;
    }

    /**
     * Create an object used by DimpCore to generate the folder tree.
     *
     * @param array $elt  The output from IMP_Tree::element().
     *
     * @return stdClass  The element object. Contains the following items:
     * <pre>
     * 'ch' (children) = Does the folder contain children? [boolean]
     *                   [DEFAULT: no]
     * 'cl' (class) = The CSS class. [string] [DEFAULT: 'base']
     * 'co' (container) = Is this folder a container element? [boolean]
     *                    [DEFAULT: no]
     * 'i' (icon) = A user defined icon to use. [string] [DEFAULT: none]
     * 'l' (label) = The folder display label. [string] [DEFAULT: 'm' val]
     * 'm' (mbox) = The mailbox value. [string]
     * 'pa' (parent) = The parent element. [string] [DEFAULT:
     *                                               DIMP.conf.base_mbox]
     * 'po' (polled) = Is the element polled? [boolean] [DEFAULT: no]
     * 's' (special) = Is this a "special" element? [boolean] [DEFAULT: no]
     * 't' (title) = The title value. [string] [DEFAULT: 'm' val]
     * 'u' (unseen) = The number of unseen messages. [integer]
     * 'un' (unsubscribed) = Is this folder unsubscribed? [boolean]
     *                       [DEFAULT: no]
     * 'v' (virtual) = Is this a virtual folder? [boolean] [DEFAULT: no]
     * </pre>
     */
    static private function _createFolderElt($elt)
    {
        $ob = new stdClass;

        if ($elt['children']) {
            $ob->ch = 1;
        }
        $ob->m = $elt['value'];
        if ($ob->m != $elt['name']) {
            $ob->l = $elt['name'];
        }
        if ($elt['parent'] != IMP_Imap_Tree::BASE_ELT) {
            $ob->pa = $elt['parent'];
        }
        if ($elt['polled']) {
            $ob->po = 1;
        }
        if ($elt['vfolder']) {
            $ob->v = 1;
        }
        if (!$elt['sub']) {
            $ob->un = 1;
        }

        $tmp = IMP::getLabel($ob->m);
        if ($tmp != $ob->m) {
            $ob->t = $tmp;
        }

        if ($elt['container']) {
            $ob->co = 1;
            $ob->cl = 'exp';
        } else {
            if ($elt['polled']) {
                $ob->u = intval($elt['unseen']);
            }

            switch ($elt['special']) {
            case IMP_Imap_Tree::SPECIAL_INBOX:
                $ob->cl = 'inbox';
                $ob->s = 1;
                break;

            case IMP_Imap_Tree::SPECIAL_TRASH:
                $ob->cl = 'trash';
                $ob->s = 1;
                break;

            case IMP_Imap_Tree::SPECIAL_SPAM:
                $ob->cl = 'spam';
                $ob->s = 1;
                break;

            case IMP_Imap_Tree::SPECIAL_DRAFT:
                $ob->cl = 'drafts';
                $ob->s = 1;
                break;

            case IMP_Imap_Tree::SPECIAL_SENT:
                $ob->cl = 'sent';
                $ob->s = 1;
                break;

            default:
                if ($elt['vfolder']) {
                    if ($GLOBALS['imp_search']->isVTrashFolder($elt['value'])) {
                        $ob->cl = 'trash';
                    } elseif ($GLOBALS['imp_search']->isVINBOXFolder($elt['value'])) {
                        $ob->cl = 'inbox';
                    }
                } elseif ($elt['children']) {
                    $ob->cl = 'exp';
                }
                break;
            }
        }

        if ($elt['user_icon']) {
            $ob->cl = 'customimg';
            $dir = empty($elt['icondir'])
                ? $GLOBALS['registry']->getImageDir()
                : $elt['icondir'];
            $ob->i = empty($dir)
                ? $elt['icon']
                : $dir . '/' . $elt['icon'];
        }

        return $ob;
    }

    /**
     * Return information about the current attachments for a message
     *
     * @param IMP_Compose $imp_compose  An IMP_Compose object.
     *
     * @return array  An array of arrays with the following keys:
     * <pre>
     * 'number' - The current attachment number
     * 'name' - The HTML encoded attachment name
     * 'type' - The MIME type of the attachment
     * 'size' - The size of the attachment in KB (string)
     * </pre>
     */
    static public function getAttachmentInfo($imp_compose)
    {
        $fwd_list = array();

        if ($imp_compose->numberOfAttachments()) {
            foreach ($imp_compose->getAttachments() as $atc_num => $data) {
                $mime = $data['part'];

                $fwd_list[] = array(
                    'number' => $atc_num,
                    'name' => htmlspecialchars($mime->getName(true)),
                    'type' => $mime->getType(),
                    'size' => $mime->getSize()
                );
            }
        }

        return $fwd_list;
    }

    /**
     * Return a list of DIMP specific menu items.
     *
     * @return array  The array of menu items.
     */
    static public function menuList()
    {
        if (isset($GLOBALS['conf']['dimp']['menu']['apps'])) {
            $apps = $GLOBALS['conf']['dimp']['menu']['apps'];
            if (is_array($apps) && count($apps)) {
                return $apps;
            }
        }
        return array();
    }

    /**
     * Build data structure needed by DimpCore javascript to display message
     * log information.
     *
     * @var string $msg_id  The Message-ID header of the message.
     *
     * @return array  An array of information that can be parsed by
     *                DimpCore.updateInfoList().
     */
    static public function getMsgLogInfo($msg_id)
    {
        $ret = array();

        foreach (IMP_Maillog::parseLog($msg_id) as $val) {
            $ret[] = array_map('htmlspecialchars', array(
                'm' => $val['msg'],
                't' => $val['action']
            ));
        }

        return $ret;
    }

}
