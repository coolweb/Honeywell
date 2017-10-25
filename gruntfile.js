module.exports = function (grunt) {

    grunt.initConfig({
        copy: {
            main: {
                files: [
                    { expand: true, src: ['core/**'], dest: 'dist/' },
                    { expand: true, src: ['desktop/**'], dest: 'dist/' },
                    { expand: true, src: ['doc/**'], dest: 'dist/' },
                    { expand: true, src: ['plugin_info/**'], dest: 'dist/' },
                    { expand: true, src: ['composer.json'], dest: 'dist/' }
                ]
            },
            vendor: {
                files: [
                    { expand: true, cwd:'dist/vendor/', src: ['**'], dest: 'dist/3rparty/' }                
                ]
            }
        },
        clean:{
            build:['dist'],
            vendor:['dist/vendor', 'dist/composer.json', 'dist/composer.lock']
        },
        phpunit: {
            classes: {
                dir: 'test'
            },
            options: {
                bin: 'vendor/bin/phpunit',
                colors: true,
                configuration: 'test/phpunit.xml'
            }
        },
        phplint: {
            good: ['core/**/*.php', 'desktop/**/*.php', 'test/**/*.php']
        },
        phpcs: {
            application: {
                src: [
                    'core/**/*.php',
                    'test/**/*.php',
                    '!core/class/honeywell.class.php',
                    '!core/class/honeywellProxy.class.php',
                    '!test/cmd.php',
                    '!test/eqLogic.php'
                ]
            },
            options: {
                bin: 'vendor/bin/phpcs',
                standard: 'PSR2'
            }
        },
        composer : {
            production: {
                options : {
                    flags: ['no-dev'],
                    cwd: 'dist'
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-phpunit');
    grunt.loadNpmTasks('grunt-phplint');
    grunt.loadNpmTasks('grunt-phpcs');
    grunt.loadNpmTasks('grunt-composer');

    grunt.registerTask('default', ['']);
    grunt.registerTask('update', 
    ['phplint', 
    'phpcs', 
    'copy:main',
    'clean:vendor']);
    grunt.registerTask('make', 
    ['clean:build', 
    'phplint', 
    'phpcs', 
    'copy:main', 
    'composer:production:install',
    'copy:vendor',
    'clean:vendor']);
};