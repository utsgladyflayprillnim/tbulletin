<?php

namespace Drupal\tbulletin\Controller;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TbulletinForumController implements ControllerInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection;
   */
  protected $database;

  public function __construct(Connection $database, ConfigFactory $config_factory){
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container){
    return new static(
      $container->get('database'),
      $container->get('config.factory')
    );
  }

  public function forumDisplay(){

    $types = $this->configFactory->get('tbulletin.settings')->get('forum_allowed_types');
    if (!is_array($types) && empty($types)){
      return new RedirectResponse(url('admin/config/tbulletin/forum', array('absolute' => TRUE)));
    }
    //Query content type belongs to forum
    $results = $this->database->query(
      'SELECT nt.type, nt.name FROM {node_type} nt WHERE nt.type IN(:types)', array(':types' => $types))->fetchAll();

    $header = array(t('forums'), t('topics'), t('posts'), t('last post'));

    $rows = array();
    foreach ($results as $result){
      $row = array();
      $row[] = l($result->name, 'forum/'.$result->type);
      $row[] = $this->database->query(
        'SELECT * FROM {node} n WHERE n.type=:type',
        array(':type' => $result->type)
        )->rowCount();
      $row[] = $this->database->query(
        'SELECT COUNT(comment_count) FROM {node_comment_statistics} ncs
        INNER JOIN {node_field_data} nfd ON nfd.nid=ncs.nid
        WHERE type=:type AND nfd.status=:status GROUP BY nfd.type',
        array(':type' => $result->type, ':status' => TRUE)
        )->fetchField();
      if ($this->database->query(
        'SELECT c.cid FROM {comment} c
        WHERE c.nid=:nid ORDER BY c.created DESC LIMIT 0,1',
        array(':nid' =>
          db_query('SELECT nfd.nid FROM {node_field_data} nfd
            WHERE nfd.type=:type AND nfd.status=:status
            ORDER BY nfd.created DESC LIMIT 0,1',
            array(':type' => $result->type, ':status' => TRUE))->fetchField()))->rowCount()){

        $last_post = $this->database->query(
          'SELECT nfd.title, nfd.created, nfd.nid, u.name, u.uid
          FROM {node_field_data} nfd
          LEFT JOIN {comment} c ON c.nid = nfd.nid
          LEFT JOIN {users} u ON u.uid=c.uid
          WHERE nfd.type=:type ORDER BY c.created DESC LIMIT 0,1',
          array(':type'=> $result->type))->fetchObject();
      }
      else{
        $last_post = $this->database->query(
          'SELECT nfd.title, nfd.created, nfd.nid, u.name, u.uid
          FROM {node_field_data} nfd
          LEFT JOIN {users} u ON u.uid=nfd.uid
          WHERE nfd.type=:type ORDER BY nfd.created DESC LIMIT 0,1',
          array(':type'=> $result->type))->fetchObject();
      }
      if ($last_post){
        $row[] = l($last_post->title, 'node/' . $last_post->nid) . '<br />'. format_date($last_post->created) .'<br />' . l($last_post->name, 'user/' . $last_post->uid);
      }
      $rows[] = $row;
    }

    $build['forums'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No Forums'),

    );
    return $build;
  }

  public function topicDisplay(){

    $header = array(
      'title' => array('data' => t('Topics')),
      'author' => array('data' => t('Author')),
      'posts' => array('data' => t('Posts')),
      'last_post' => array('data' => t('Last Post')),
    );

    $query = db_select('node_field_data', 'nfd')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('nfd', array('title', 'nid', 'created'));

    $query->condition('nfd.type', arg(1));
    $query->condition('nfd.status', 1);
    $query->limit(2);
    $query->orderBy('nfd.created', 'DESC');
    $results = $query->execute()->fetchAll();

    $options = array();
    foreach($results as $result){
      $options[$result->nid] = array(
        'title' => array(
          'data' => array(
            '#type' => 'link',
            '#title' => $result->title,
            '#href' => 'node/' . $result->nid,
          ),
        ),
      );
      $author = db_query('SELECT u.name, u.uid, nfd.created FROM {node_field_data} nfd LEFT JOIN {users} u ON nfd.uid=u.uid WHERE nfd.nid=:nid', array(':nid' => $result->nid))->fetchObject();
      $options[$result->nid]['author'] = l($author->name, 'user/'.$author->uid);
      $comments = db_query('SELECT SUM(comment_count) FROM {node_comment_statistics} WHERE nid=:nid', array(':nid' => $result->nid))->fetchField();
      $options[$result->nid]['posts'] = $comments;

      if ( $comments ){
        $last_post = db_query('SELECT u.name, u.uid, c.created FROM {comment} c LEFT JOIN {users} u ON c.uid=u.uid WHERE c.nid=:nid ORDER BY c.created DESC LIMIT 0,1', array(':nid' => $result->nid))->fetchObject();
      }
      else{
        $last_post = $author;
      }

      $options[$result->nid]['last_post'] = format_date($last_post->created).'<br />'. l($last_post->name, 'user/'.$last_post->uid)  ;
    }

    $form['topics'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No topics available!'),
    );

    $form['pager'] = array('#theme' => 'pager');
    return $form;
  }
}
