<?php
/**
 * This file is part of OXID eShop Community Edition.
 *
 * OXID eShop Community Edition is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eShop Community Edition is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2016
 * @version   OXID eShop CE
 */

namespace OxidEsales\Eshop\Application\Controller\Admin;

use oxRegistry;
use oxDb;
use oxField;
use Exception;
/**
 * Class controls article assignment to action
 */
class ActionsMainAjax extends \ajaxListComponent
{

    /**
     * If true extended column selection will be build
     *
     * @var bool
     */
    protected $_blAllowExtColumns = true;

    /**
     * Columns array
     *
     * @var array
     */
    protected $_aColumns = array('container1' => array( // field , table,         visible, multilanguage, ident
        array('oxartnum', 'oxarticles', 1, 0, 0),
        array('oxtitle', 'oxarticles', 1, 1, 0),
        array('oxean', 'oxarticles', 1, 0, 0),
        array('oxmpn', 'oxarticles', 0, 0, 0),
        array('oxprice', 'oxarticles', 0, 0, 0),
        array('oxstock', 'oxarticles', 0, 0, 0),
        array('oxid', 'oxarticles', 0, 0, 1)
    ),
                                 'container2' => array(
                                     array('oxartnum', 'oxarticles', 1, 0, 0),
                                     array('oxsort', 'oxactions2article', 1, 0, 0),
                                     array('oxtitle', 'oxarticles', 1, 1, 0),
                                     array('oxean', 'oxarticles', 1, 0, 0),
                                     array('oxmpn', 'oxarticles', 0, 0, 0),
                                     array('oxprice', 'oxarticles', 0, 0, 0),
                                     array('oxstock', 'oxarticles', 0, 0, 0),
                                     array('oxid', 'oxactions2article', 0, 0, 1)
                                 )
    );

    /**
     * Returns SQL query for data to fetc
     *
     * @return string
     */
    protected function _getQuery()
    {
        $myConfig = $this->getConfig();
        $oDb = oxDb::getDb();
        // looking for table/view
        $sArtTable = $this->_getViewName('oxarticles');
        $sView = $this->_getViewName('oxobject2category');

        $sSelId = oxRegistry::getConfig()->getRequestParameter('oxid');
        $sSynchSelId = oxRegistry::getConfig()->getRequestParameter('synchoxid');

        // category selected or not ?
        if (!$sSelId) {
            //performance
            $sQAdd = " from $sArtTable where 1 ";
            $sQAdd .= $myConfig->getConfigParam('blVariantsSelection') ? '' : " and $sArtTable.oxparentid = '' ";
        } else {
            // selected category ?
            if ($sSynchSelId && $sSelId != $sSynchSelId) {

                $sQAdd = " from {$sView} left join $sArtTable on ";
                $blVariantsSelectionParameter = $myConfig->getConfigParam('blVariantsSelection');
                $sSqlIfTrue = " ( $sArtTable.oxid={$sView}.oxobjectid or $sArtTable.oxparentid={$sView}.oxobjectid) ";
                $sSqlIfFalse = " $sArtTable.oxid={$sView}.oxobjectid ";
                $sQAdd .= $blVariantsSelectionParameter ? $sSqlIfTrue : $sSqlIfFalse;
                $sQAdd .= " where {$sView}.oxcatnid = " . $oDb->quote($sSelId);
            } else {

                $sQAdd = " from {$sArtTable} left join oxactions2article " .
                         "on {$sArtTable}.oxid=oxactions2article.oxartid " .
                         " where oxactions2article.oxactionid = " . $oDb->quote($sSelId) .
                         " and oxactions2article.oxshopid = '" . $myConfig->getShopID() . "' ";
            }
        }

        if ($sSynchSelId && $sSynchSelId != $sSelId) {
            $sQAdd .= " and {$sArtTable}.oxid not in ( select oxactions2article.oxartid from oxactions2article " .
                      " where oxactions2article.oxactionid = " . $oDb->quote($sSynchSelId) .
                      " and oxactions2article.oxshopid = '" . $myConfig->getShopID() . "' ) ";
        }

        return $sQAdd;
    }

    /**
     * Adds filter SQL to current query
     *
     * @param string $sQ query to add filter condition
     *
     * @return string
     */
    protected function _addFilter($sQ)
    {
        $sQ = parent::_addFilter($sQ);

        // display variants or not ?
        if ($this->getConfig()->getConfigParam('blVariantsSelection')) {
            $sQ .= ' group by ' . $this->_getViewName('oxarticles') . '.oxid ';

            $oStr = getStr();
            if ($oStr->strpos($sQ, "select count( * ) ") === 0) {
                $sQ = "select count( * ) from ( {$sQ} ) as _cnttable";
            }
        }

        //echo $sQ;
        return $sQ;
    }

    /**
     * Returns SQL query addon for sorting
     *
     * @return string
     */
    protected function _getSorting()
    {
        $sOxIdParameter = oxRegistry::getConfig()->getRequestParameter('oxid');
        $sSynchOxidParameter = oxRegistry::getConfig()->getRequestParameter('synchoxid');
        if ($sOxIdParameter && !$sSynchOxidParameter) {
            return 'order by oxactions2article.oxsort ';
        }

        return parent::_getSorting();
    }

    /**
     * Removes article from Promotions list
     */
    public function removeArtFromAct()
    {
        $aChosenArt = $this->_getActionIds('oxactions2article.oxid');
        $sOxid = oxRegistry::getConfig()->getRequestParameter('oxid');

        $this->_getOxRssFeed()->removeCacheFile($sOxid);

        if (oxRegistry::getConfig()->getRequestParameter('all')) {
            $sQ = parent::_addFilter("delete oxactions2article.* " . $this->_getQuery());
            oxDb::getDb()->Execute($sQ);
        } elseif (is_array($aChosenArt)) {
            $sChosenArticles = implode(", ", oxDb::getDb()->quoteArray($aChosenArt));
            $sQ = "delete from oxactions2article where oxactions2article.oxid in (" . $sChosenArticles . ") ";
            oxDb::getDb()->Execute($sQ);
        }
    }

    /**
     * Adds article to Promotions list
     *
     * @return bool Whether any article was added to action.
     *
     * @throws Exception
     */
    public function addArtToAct()
    {
        $myConfig = $this->getConfig();
        $aArticles = $this->_getActionIds('oxarticles.oxid');
        $soxId = oxRegistry::getConfig()->getRequestParameter('synchoxid');

        $this->_getOxRssFeed()->removeCacheFile($soxId);

        if (oxRegistry::getConfig()->getRequestParameter('all')) {
            $sArtTable = $this->_getViewName('oxarticles');
            $aArticles = $this->_getAll($this->_addFilter("select $sArtTable.oxid " . $this->_getQuery()));
        }

        oxDb::getDb()->startTransaction();
        try {
            $database = oxDb::getDb();
            $sArtTable = $this->_getViewName('oxarticles');
            $sQ = "select max(oxactions2article.oxsort) from oxactions2article join {$sArtTable} " .
                  "on {$sArtTable}.oxid=oxactions2article.oxartid " .
                  "where oxactions2article.oxactionid = " . $database->quote($soxId) .
                  " and oxactions2article.oxshopid = '" . $myConfig->getShopId() .
                  "'and $sArtTable.oxid is not null";

            $iSort = ((int) $database->getOne($sQ)) + 1;

            $articleAdded = false;
            if ($soxId && $soxId != "-1" && is_array($aArticles)) {
                $sShopId = $myConfig->getShopId();
                foreach ($aArticles as $sAdd) {
                    $oNewGroup = oxNew('oxBase');
                    $oNewGroup->init('oxactions2article');
                    $oNewGroup->oxactions2article__oxshopid = new oxField($sShopId);
                    $oNewGroup->oxactions2article__oxactionid = new oxField($soxId);
                    $oNewGroup->oxactions2article__oxartid = new oxField($sAdd);
                    $oNewGroup->oxactions2article__oxsort = new oxField($iSort++);
                    $oNewGroup->save();
                }
                $articleAdded = true;
            }
        } catch (Exception $exception) {
            oxDb::getDb()->rollbackTransaction();
            throw $exception;
        }
        oxDb::getDb()->commitTransaction();

        return $articleAdded;
    }

    /**
     * Sets sorting position for current action article
     */
    public function setSorting()
    {
        $myConfig = $this->getConfig();
        $sArtTable = $this->_getViewName('oxarticles');
        $sSelId = oxRegistry::getConfig()->getRequestParameter('oxid');
        $sSelect = "select * from $sArtTable left join oxactions2article on $sArtTable.oxid=oxactions2article.oxartid ";
        $sSelect .= "where oxactions2article.oxactionid = " . oxDb::getDb()->quote($sSelId) .
                    " and oxactions2article.oxshopid = '" . $myConfig->getShopID() . "' " . $this->_getSorting();

        $oList = oxNew("oxlist");
        $oList->init("oxbase", "oxactions2article");
        $oList->selectString($sSelect);

        // fixing indexes
        $iSelCnt = 0;
        $aIdx2Id = array();
        foreach ($oList as $sKey => $oSel) {
            if ($oSel->oxactions2article__oxsort->value != $iSelCnt) {
                $oSel->oxactions2article__oxsort->setValue($iSelCnt);

                // saving new index
                $oSel->save();
            }
            $aIdx2Id[$iSelCnt] = $sKey;
            $iSelCnt++;
        }

        //
        if (($iKey = array_search(oxRegistry::getConfig()->getRequestParameter('sortoxid'), $aIdx2Id)) !== false) {
            $iDir = (oxRegistry::getConfig()->getRequestParameter('direction') == 'up') ? ($iKey - 1) : ($iKey + 1);
            if (isset($aIdx2Id[$iDir])) {

                // exchanging indexes
                $oDir1 = $oList->offsetGet($aIdx2Id[$iDir]);
                $oDir2 = $oList->offsetGet($aIdx2Id[$iKey]);

                $iCopy = $oDir1->oxactions2article__oxsort->value;
                $oDir1->oxactions2article__oxsort->setValue($oDir2->oxactions2article__oxsort->value);
                $oDir2->oxactions2article__oxsort->setValue($iCopy);

                $oDir1->save();
                $oDir2->save();
            }
        }

        $sQAdd = $this->_getQuery();

        $sQ = 'select ' . $this->_getQueryCols() . $sQAdd;
        $sCountQ = 'select count( * ) ' . $sQAdd;

        $this->_outputResponse($this->_getData($sCountQ, $sQ));
    }

    /**
     * Getter for the rss feed handler.
     *
     * @return oxRssFeed The rss feed handler.
     */
    protected function _getOxRssFeed()
    {
        return oxNew('oxRssFeed');
    }

}
