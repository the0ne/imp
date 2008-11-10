<?php
/**
 * The IMP_Horde_Mime_Viewer_alternative class renders out messages from
 * multipart/alternative content types (RFC 2046 [5.1.4]).
 *
 * $Horde: imp/lib/MIME/Viewer/alternative.php,v 1.66 2008/01/02 11:12:45 jan Exp $
 *
 * Copyright 2002-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package Horde_Mime
 */
class IMP_Horde_Mime_Viewer_alternative extends Horde_Mime_Viewer_Driver
{
    /**
     * The content-type of the preferred part.
     * Default: application/octet-stream
     *
     * @var string
     */
    protected $_contentType = 'application/octet-stream';

    /**
     * Render out the currently set contents.
     *
     * @param array $params  An array with a reference to a MIME_Contents
     *                       object.
     *
     * @return string  The rendered text in HTML.
     */
    public function render($params)
    {
        $contents = &$params[0];

        $display_id = null;
        $summaryList = array();
        $text = '';

        /* Look for a displayable part.
         * RFC 2046: We show the LAST choice that can be displayed inline. */
        $partList = $this->mime_part->getParts();
        foreach ($partList as $part) {
            if ($contents->canDisplayInline($part)) {
                $text = $contents->renderMIMEPart($part);
                $this->_contentType = $part->getType();
                $display_id = $part->getMIMEId();
            }
        }

        /* Show links to alternative parts. */
        if (($text === null) || (count($partList) > 1)) {
            if ($text === null) {
                $text = '<em>' . _("There are no alternative parts that can be displayed.") . '</em>';
            }

            /* Generate the list of summaries to use. */
            foreach ($partList as $part) {
                $id = $part->getMIMEId();
                if ($id && $id != $display_id) {
                    $summary = $contents->partSummary($part);
                    /* We don't want to show the MIME ID for alt parts. */
                    if (!empty($summary)) {
                        array_splice($summary, 1, 1);
                        $summaryList[] = $summary;
                    }
                }
            }

            /* Make sure there is at least one summary before showing the
             * alternative parts. */
            $alternative_display = $GLOBALS['prefs']->getValue('alternative_display');
            if (!empty($summaryList) &&
                !$this->viewAsAttachment() &&
                $alternative_display != 'none') {
                $status_array = array();
                $status = _("Alternative parts for this section:");
                if ($contents->showSummaryLinks()) {
                    require_once 'Horde/Help.php';
                    $status .= '&nbsp;&nbsp;' . Help::link('imp', 'alternative-msg');
                }
                $status_array[] = $status;
                $status = '<table border="0" cellspacing="1" cellpadding="1">';
                foreach ($summaryList as $summary) {
                    $status .= '<tr valign="middle">';
                    foreach ($summary as $val) {
                        if (!empty($val)) {
                            $status .= "<td>$val&nbsp;</td>\n";
                        }
                    }
                    $status .= "</tr>\n";
                }
                $status .= '</table>';
                $status_array[] = $status;
                $status_msg = $this->formatStatusMsg($status_array, Horde::img('mime/binary.png', _("Multipart/alternative"), null, $GLOBALS['registry']->getImageDir('horde')), false);
                switch ($alternative_display) {
                case 'above':
                    $text = $status_msg . $text;
                    break;

                case 'below':
                    $text .= $status_msg;
                    break;
                }
            }
        }

        /* Remove attachment information for the displayed part if we
         * can. */
        if (is_callable(array($contents, 'removeAtcEntry'))) {
            $contents->removeAtcEntry($this->mime_part->getMIMEId());
        }

        return $text;
     }

    /**
     * Return the content-type.
     *
     * @return string  The content-type of the message.
     *                 Returns 'application/octet-stream' until actual
     *                 content type of the message can be determined.
     */
    public function getType()
    {
        return $this->_contentType;
    }
}
