pipeline {
  options {
    skipDefaultCheckout()
  }
  agent {
    kubernetes {
      label 'mautic-hosted-build'
      inheritFrom 'with-mysql'
      containerTemplate {
        name 'hosted-tester'
        image 'us.gcr.io/mautic-ma/mautic_tester:master'
        ttyEnabled true
        command 'cat'
      }
    }
  }
  stages {
    stage('Download and combine') {
      steps {
        container('hosted-tester') {
          checkout changelog: false, poll: false, scm: [$class: 'GitSCM', branches: [[name: 'beta']], doGenerateSubmoduleConfigurations: false, extensions: [[$class: 'SubmoduleOption', disableSubmodules: false, parentCredentials: true, recursiveSubmodules: true]], submoduleCfg: [], userRemoteConfigs: [[credentialsId: '1a066462-6d24-4247-bef6-1da084c8f484', url: 'git@github.com:mautic-inc/mautic-cloud.git']]]
          sh('rm -r plugins/CustomObjectsBundle || true; mkdir -p plugins/CustomObjectsBundle && chmod 777 plugins/CustomObjectsBundle')
          dir('plugins/CustomObjectsBundle') {
            checkout scm
          }
        }
      }
    }
    stage('Build') {
      steps {
        container('hosted-tester') {
          ansiColor('xterm') {
            sh """
              composer install --ansi
            """
            dir('plugins/CustomObjectsBundle') {
              sh("composer install --ansi")
            }
          }
        }
      }
    }
    stage('Styling') {
      steps {
        container('hosted-tester') {
          ansiColor('xterm') {
            dir('plugins/CustomObjectsBundle') {
              sh """
                vendor/bin/ecs check .
              """
            }
          }
        }
      }
    }
    stage('Test') {
      steps {
        container('hosted-tester') {
          ansiColor('xterm') {
            sh """
              mysql -h 127.0.0.1 -e 'CREATE DATABASE mautictest; CREATE USER travis@"%"; GRANT ALL on mautictest.* to travis@"%"; GRANT SUPER ON *.* TO travis@"%";'
              echo "<?php
              \\\$parameters = array(
                  'db_driver' => 'pdo_mysql',
                  'db_host' => '127.0.0.1',
                  'db_port' => 3306,
                  'db_name' => 'mautictest',
                  'db_user' => 'travis',
                  'db_password' => '',
                  'db_table_prefix' => '',
                  'hosted_plan' => 'pro',
                  'custom_objects_enabled' => true
              );" > app/config/local.php
              export SYMFONY_ENV="test"
              bin/phpunit -d memory_limit=2048M --bootstrap vendor/autoload.php --configuration plugins/CustomObjectsBundle/phpunit.xml --fail-on-warning  --testsuite=all
            """
          }
        }
      }
    }
    stage('Static Analysis') {
      steps {
        container('hosted-tester') {
          ansiColor('xterm') {
            dir('plugins/CustomObjectsBundle') {
              sh """
                composer run-script phpstan
              """
            }
          }
        }
      }
    }
    stage('Fill Hash') {
      when {
        not {
          changeRequest()
        }
        anyOf {
          branch 'beta'
          branch 'staging'          
          branch 'master';
        }
      }
      steps {
        script {
          echo "Updating submodule in mautic-cloud for branch ${env.BRANCH_NAME}"
          sshagent (credentials: ['1a066462-6d24-4247-bef6-1da084c8f484']) {
            sh '''
              git config --global user.email "9725490+mautibot@users.noreply.github.com"
              git config --global user.name "Jenkins"
              git clone git@github.com:mautic-inc/mautic-cloud.git -b $BRANCH_NAME
              cd mautic-cloud
              git submodule update --init plugins/CustomObjectsBundle/
              cd plugins/CustomObjectsBundle/
              git pull origin $BRANCH_NAME
              SUBMODULE_COMMIT=$(git log -1 | awk 'NR==1{print $2}')
              cd ../..
              git add plugins/CustomObjectsBundle
              git commit -m "Submodule updated with commit $SUBMODULE_COMMIT"
              git push
            '''
          }
        }
      }
    }    
  }
}
