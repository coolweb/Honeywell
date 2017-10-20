module.exports = function (grunt) {

    grunt.initConfig({
        copy: {
            main: {
                files: [
                    { expand: true, src: ['core/**'], dest: 'dist/' },
                    { expand: true, src: ['desktop/**'], dest: 'dist/' },
                    { expand: true, src: ['doc/**'], dest: 'dist/' },
                    { expand: true, src: ['plugin_info/**'], dest: 'dist/' },
                    { expand: true, cwd: 'vendor/psr/', src: ['**'], dest: 'dist/3rparty/psr/' },
                    { expand: true, cwd: 'vendor/container-interop/', src: ['**'], dest: 'dist/3rparty/container-interop/' },
                    { expand: true, cwd: 'vendor/php-di/', src: ['**'], dest: 'dist/3rparty/php-di/' },
                    { expand: true, cwd: 'vendor/', src: ['autoload.php'], dest: 'dist/3rparty/' },
                    { expand: true, cwd: 'vendor/composer/', src: ['**'], dest: 'dist/3rparty/composer' }
                ]
            }
        },
        clean: ['dist'],
        phpunit: {
            classes: {
                dir: 'test'
            },
            options: {
                bin: 'vendor/bin/phpunit',
                colors: true
            }
        },
        phplint: {
            good: ['core/**/*.php', 'desktop/**/*.php', 'test/**/*.php']
        },
        phpcs: {
            application: {
                src: ['core/**/*.php', 'desktop/**/*.php']
            },
            options: {
                bin: 'vendor/bin/phpcs',
                standard: './custom_ruleset.xml'
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-phpunit');
    grunt.loadNpmTasks('grunt-phplint');
    grunt.loadNpmTasks('grunt-phpcs');

    grunt.registerTask('default', ['']);
    grunt.registerTask('make', ['clean', 'phplint', 'phpcs', 'copy'])
};