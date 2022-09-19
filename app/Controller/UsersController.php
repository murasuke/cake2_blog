<?php
// app/Controller/UsersController.php
App::uses('AppController', 'Controller');

class UsersController extends AppController
{
    //どのヘルパーとコンポーネントを使うか
    public $helpers = array('Html', 'Form', 'Flash');
    public $components = array('Flash', 'Auth');

    public function beforeFilter()
    {
        //未ログイン者が見れるページ(それ以外はリダイレクト先に飛ぶ)
        parent::beforeFilter();
        $this->Auth->allow('add', 'login', 'logout');
    }

    public function add()
    {
        //postで送られてきた情報をユーザーテーブルに保存
        if ($this->request->is('post')) {
            $this->User->create();
            if ($this->User->save($this->request->data)) {
                $this->Flash->success(__('会員登録が完了しました'));
                return $this->redirect(
                    array(
                        'controller' => 'posts', 'action' => 'index'
                    )
                );
            }
            $this->Flash->error(__('登録に失敗しました。再度お試しください'));
        }
    }

    public function login()
    {
        //送られてきた情報と比べログイン処置
        //ここでハッシュ化されたパスワードと比べる
        if ($this->request->is('post')) {
            if ($this->Auth->login()) {
                $this->Flash->success(__('ログインに成功しました'));
                return $this->redirect(
                    $this->Auth->redirect(array(
                        'controller' => 'posts', 'action' => 'index'
                    ))
                );
            }
            $this->Flash->error(__('メールアドレスもしくはパスワードが違います'));
        }
    }

    public function logout()
    {
        //ログアウト処理　今回は投稿一覧に飛ぶようにしています。
        if ($this->Auth->login()) {
            $this->Flash->success(__('ログアウトしました'));
            $this->redirect(
                $this->Auth->logout(array(
                    'controller' => 'posts',
                    'action' => 'index'
                ))
            );
        }
        $this->redirect(
            $this->Auth->redirect(array(
                'controller' => 'posts',
                'action' => 'index'
            ))
        );
    }
}
