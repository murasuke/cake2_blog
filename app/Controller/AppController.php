<?php

/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 */

App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		https://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    public $components = array(
        'DebugKit.Toolbar' => array('panels' => array('history' => false)),
        'Flash',
        'Session',
        //ログイン後、ログアウト後にどのような処理を行うか
        'Auth' => array(
            'loginRedirect' => array(
                //UsersControllerに記述したログイン処理
                'controller' => 'users',
                'action' => 'login'
            ),
            //UsersControllerに記述したログアウト処理
            'logoutRedirect' => array(
                'controller' => 'posts',
                'action' => 'index',
                'home'
            ),
            //パスワードのハッシュ化
            'authenticate' => array(
                'Form' => array(
                    'userModel' => 'User',
                    'passwordHasher' => 'Blowfish',
                    'hashType' => 'md5',
                    'fields' => array(
                        'username' => 'email',
                        'password' => 'password'
                    )
                )
            ),
            'authorize' => array('Controller')
        )
    );

    public function isAuthorized($user)
    {
        // Admin can access every action
        if (isset($user['role']) && $user['role'] === 'admin') {
            return true;
        }

        // Default deny
        return false;
    }
    public $helpers = array('Html', 'Form', 'Session');
    public function beforeFilter() {
        $this->Auth->allow('index', 'view');
        //ログイン者の名前表示する
        $this->set('auth', $this->Auth->user());
        //メールアドレスでログインするためカラム切り替え
        $this->Auth->authenticate = array(
            'Form' => array(
                'fields' => array('username' => 'email', 'password' => 'password')
            )
        );
    }

    public $settings = array(
        'fields' => array(
            'username' => 'username',
            'password' => 'password'
        ),
        'userModel' => 'User',
        'userFields' => null,
        'scope' => array(),
        'recursive' => 0,
        'contain' => null,
        'passwordHasher' => 'Blowfish'
    );
}
