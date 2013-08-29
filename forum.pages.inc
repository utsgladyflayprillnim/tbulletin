<?php

function tbulletin_forum_page(){

  $query = db_select( 'node_type', 'nt' );
  $query->fields( 'nt', array('name', 'type') );
  $query->condition( 'type', array('south_lottery', 'middle_lottery', 'north_lottery'), 'IN' );
  $results = $query->execute()->fetchAll();

  $rows = array();
  $cols = array();
  $query = 'SELECT * FROM {node} n WHERE type=:type';

  $query_comment = 'SELECT SUM(comment_count) FROM {node_field_data} n ';
  $query_comment .= 'INNER JOIN {node_comment_statistics} ncs ON ncs.nid=n.nid ';
  $query_comment .= 'WHERE type=:type AND n.status=:status GROUP BY n.nid';

  // Query last post if comments was not found
  $query_last_post = 'SELECT nfd.nid FROM {node_field_data} nfd ';
  $query_last_post .= 'WHERE nfd.type=:type AND nfd.status=:status ';
  $query_last_post .= 'ORDER BY nfd.created DESC LIMIT 0,1';

  foreach( $results as $result ){
    $cols[] = l($result->name, 'forum/'.$result->type);
    $cols[] = db_query($query, array(':type' => $result->type))->rowCount();

    $comments = db_query($query_comment, array(':type' => $result->type, ':status' => TRUE))->fetchField();
    $cols[] = $comments;

    if (db_query('SELECT c.cid FROM {comment} c WHERE c.nid=:nid ORDER BY c.created DESC LIMIT 0,1', array(':nid' => db_query($query_last_post, array(':type' => $result->type, ':status' => TRUE))->fetchField()))->rowCount()){

      $last_post = db_query('SELECT nfd.title, nfd.nid, u.name, u.uid FROM {node_field_data} nfd LEFT JOIN {comment} c ON c.nid = nfd.nid LEFT JOIN {users} u ON u.uid=c.uid WHERE nfd.type=:type ORDER BY c.created DESC LIMIT 0,1', array(':type'=> $result->type))->fetchObject();

    }
    else{
      $last_post = db_query('SELECT nfd.title, nfd.nid, u.name, u.uid FROM {node_field_data} nfd LEFT JOIN {users} u ON u.uid=nfd.uid WHERE nfd.type=:type ORDER BY nfd.created DESC LIMIT 0,1', array(':type'=> $result->type))->fetchObject();
    }
    if ($last_post){
      $cols[] = l($last_post->title, 'node/' . $last_post->nid) . t('By') . l($last_post->name, 'user/' . $last_post->uid);
    }

    $rows[] = $cols;
    $cols = array();

  }

  return theme( 'table', array( 'header'=>array(t('forums'), t('topics'), t('posts'), t('last post')), 'rows' => $rows) );
}


function tbulletin_topic_page(){

  drupal_set_breadcrumb(array(l(t('Home'), NULL), l(t('forum'), '/forum')));

  $query = db_select('node_field_data', 'nfd')
    ->extend('Drupal\Core\Database\Query\PagerSelectExtender');
  $query->fields('nfd', array('title', 'nid', 'created'));

  $query->condition('nfd.type', arg(1));
  $query->condition('nfd.status', 1);
  $query->limit(2);
  $query->orderBy('nfd.created', 'DESC');
  $results = $query->execute()->fetchAll();

  $rows = array();
  foreach ($results as $result){
    $cols[] = l( $result->title, 'node/' . $result->nid);

    $comments = db_query('SELECT SUM(comment_count) FROM {node_comment_statistics} WHERE nid=:nid', array(':nid' => $result->nid))->fetchField();
    $cols[] = $comments;
    if ( $comments ){
      $last_post = db_query('SELECT u.name, u.uid, c.created FROM {comment} c LEFT JOIN {users} u ON c.uid=u.uid WHERE c.nid=:nid ORDER BY c.created DESC LIMIT 0,1', array(':nid' => $result->nid))->fetchObject();
    }
    else{
      $last_post = db_query('SELECT u.name, u.uid, nfd.created FROM {node_field_data} nfd LEFT JOIN {users} u ON nfd.uid=u.uid WHERE nfd.nid=:nid', array(':nid' => $result->nid))->fetchObject();
    }
    $cols[] = t('by') . '&nbsp;' . l($last_post->name, 'user/'.$last_post->uid) . '&nbsp;' . date('d/m/Y h:i:s', $last_post->created);
    $rows[] = $cols;
    $cols = array();
  }

  return theme('table', array('header' => array(t('topics'), t('posts'), t('last post')), 'rows' => $rows));
}


function tbulletin_topic_admin_page(){
  return drupal_get_form('tbulletin_topic_admin_page_form');
}

function tbulletin_topic_admin_page_form($form, &$form_state){
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
