<?php
/**
 * Класс экшена для генерации Feed-ленты:
 *
 * @category YupeAction
 * @package  YupeCMS
 * @author   AKulikov <tuxuls@gmail.com>
 * @license  BSD http://ru.wikipedia.org/wiki/%D0%9B%D0%B8%D1%86%D0%B5%D0%BD%D0%B7%D0%B8%D1%8F_BSD
 * @version  0.1 (dev)
 * @link     http://yupe.ru
 *
 **/
class FeedAction extends CAction
{
    /**
     * Для использования данного екшена,
     * вам потребуется вставить следующий код
     * в ваш контроллер (пример):
     *
     *     public function actions()
     *     {
     *         return array(
     *             'atomfeed' => array(
     *                 'class'        => 'application.modules.yupe.components.actions.FeedAction',
     *                 'data'         => News::model()->published()->findAll(),
     *                 // Параметр title по умолчанию берётся из настроек приложения
     *                 //'title'        => Yii::t('YupeModule.yupe', 'Название моего сайта'),
     *                 // Параметр description по умолчанию берётся из настроек приложения
     *                 //'description'  => Yii::t('YupeModule.yupe', 'Лента новостей сайта'),
     *                 // Параметр link по умолчанию берётся как Yii::app()->request->getBaseUrl(true)
     *                 //'link' => Yii::app()->request->getBaseUrl(true),
     *                 'itemFields'   => array(
     *                     // author_object, если не задан - у
     *                     // item-елемента запросится author_nickname
     *                     'author_object'   => 'user',
     *                     // 'author_nickname' => 'nick_name', 
     *                     'author_nickname' => 'nick_name',
     *                     'content'         => 'full_text',
     *                     'datetime'        => 'creation_date',
     *                     'link'            => '/news/news/show',
     *                     'linkParams'      => array('title' => 'alias'),
     *                     'title'           => 'title',
     *                     'updated'         => 'change_date',
     *                 ),
     *             ),
     *         );
     *     }
     **/

    public $data;
    public $description;
    public $itemFields = array(
        // author_object, если не задан - у
        // item-елемента запросится author_nickname
        'author_object'   => null,
        // author nick_name param
        'author_nickname' => 'nick_name',
        'content'         => null,
        'datetime'        => null,
        'link'            => null,
        'linkParams'      => null,
        'title'           => null,
        'updated'         => null,

    );
    public $link;
    public $title;

    /**
     * Запуск экшена
     *
     * здесь мы производим рендеринг нашей ленты:
     *
     * @return void
     **/
    public function run()
    {
        // Сбрасываем CSS и JS - они нам не нужны:
        Yii::app()->clientScript->reset();

        // Подключаем необходимые библиотеки:
        Yii::import('application.modules.yupe.extensions.feed.*');

        /**
         * Определяем заголовок для ленты:
         */
        $this->title = empty($this->title)
            ? Yii::app()->name
            : $this->title;

        /**
         * Опеределяем описание для ленты:
         */
        $this->description = empty($this->description)
            ? Yii::app()->getModule('yupe')->siteDescription
            : $this->description;

        /**
         * Опеределяем link для ленты:
         */
        $this->link = empty($this->link)
            ? Yii::app()->request->getBaseUrl('true')
            : $this->link;

        /**
         * Опеределяем author_nickname для итемов:
         */
        $this->itemFields['author_nickname'] = !isset($this->itemFields['author_nickname'])
            ? 'nick_name'
            : $this->itemFields['author_nickname'];
        
        $feed = new EFeed(EFeed::ATOM);
 
        $feed->title = $this->title;
        $feed->link = $this->link;
        $feed->description = $this->description;
         
        $feed->addChannelTag('updated', date(DATE_ATOM, time()));
        if (count($this->data) > 0) {
            foreach ($this->data as $feedItem) {
                /**
                 * Создаём объект item'а
                 */
                $item = $feed->createNewItem();
                /**
                 * Устанавливаем заголовок для $item
                 */
                if (!empty($this->itemFields['title']))
                    $item->title = $feedItem{$this->itemFields['title']};
                /**
                 * Устанавливаем автора для $item
                 */
                if (!empty($this->itemFields['author_object']))
                    $item->addTag('author', $feedItem->{$this->itemFields['author_object']}->{$this->itemFields['author_nickname']});
                elseif (empty($this->itemFields['author_object'])
                    && !empty($this->itemFields['author_nickname'])
                    && property_exists($feedItem, $this->itemFields['author_nickname']))
                    $item->addTag('author', $feedItem->{$this->itemFields['author_nickname']});

                /**
                 * Устанавливаем дату для $item
                 */
                if (!empty($this->itemFields['datetime']))
                    $item->addTag('published', (new DateTime($feedItem->{$this->itemFields['datetime']}))->format(DateTime::ATOM));

                /**
                 * Устанавливаем дату изменения для $item
                 */
                if (!empty($this->itemFields['updated']))
                    $item->date = $feedItem->{$this->itemFields['updated']};

                /**
                 * Устанавливаем контент для $item
                 */
                if (!empty($this->itemFields['content']))
                    $item->description = $feedItem->{$this->itemFields['content']};

                /**
                 * Устанавливаем ссылку для $item
                 */
                if (!empty($this->itemFields['link'])) {
                    $link = array();
                    foreach ($this->itemFields['linkParams'] as $key => $param)
                        $link[$key] = $feedItem->$param;
                    $item->link = Yii::app()->createAbsoluteUrl($this->itemFields['link'], $link);
                }

                if (!empty($this->itemFields['updated']))
                    $item->date = (new DateTime($feedItem->{$this->itemFields['datetime']}))->format(DateTime::ATOM);
                 
                $feed->addItem($item);
            }
        } else {
            /**
             * Создаём объект item'а
             */
            $item = $feed->createNewItem();

            /**
             * Устанавливаем контент для $item
             */
            if (!empty($this->itemFields['content']))
                $item->description = Yii::t('YupeModule.yupe', 'Записей нет.');

            $item->date = (new DateTime('NOW'))->format(DateTime::ATOM);
             
            $feed->addItem($item);
        }
         
        $feed->generateFeed();
        Yii::app()->end();
    }
}