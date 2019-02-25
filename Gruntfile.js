'use strict';

module.exports = function(grunt) {

  var fileRevs = grunt.file.readJSON('filerevs.json');

  var config;

  config = {
    sass: 'assets/scss',
    css: 'assets/css',
    js: 'assets/js',
    //scripts: 'scripts',
    cssVersionAdmin: fileRevs['assets/css/temporal-admin.min.css'],
    cssVersion: fileRevs['assets/css/temporal.min.css'],
    jsVersionAdmin: fileRevs['assets/js/temporal-admin.min.js'],
    jsVersionAjax: fileRevs['assets/js/temporal-ajax.min.js'],
    jsVersion: fileRevs['assets/js/temporal.min.js']
  };

  //grunt.log.write(fileRevs.css).ok();

  // Project configuration.
  grunt.initConfig({
    config: config,
    pkg: grunt.file.readJSON('package.json'),
    asset_version_json: {
      assets: {
        options: {
          algorithm: 'sha1',
          length: 8,
          format: false,
          rename: true
        },
        src: ['<%= config.css %>/temporal-admin.min.css',
              '<%= config.css %>/temporal.min.css',
              '<%= config.js %>/temporal-admin.min.js',
              '<%= config.js %>/temporal-ajax.min.js',
              '<%= config.js %>/temporal.min.js'],
        dest: 'filerevs.json'
      }
    },
    sass: {
      dev: {
        options: {
          style: 'expanded',
          sourcemap: 'none'
        },
        files: {
          '<%= config.css %>/temporal-admin.<%= config.cssVersionAdmin %>.min.css': '<%= config.sass %>/temporal-admin.scss',
          '<%= config.css %>/temporal.<%= config.cssVersion %>.min.css': '<%= config.sass %>/temporal.scss'
        }
      },
      prod: {
        options: {
          style: 'compressed',
          sourcemap: 'none'
        },
        files: {
          '<%= config.css %>/temporal-admin.<%= config.cssVersionAdmin %>.min.css': '<%= config.sass %>/temporal-admin-concatenated.scss',
          '<%= config.css %>/temporal.<%= config.cssVersion %>.min.css': '<%= config.sass %>/temporal.scss'
        }
      }
    },
    concat: {
      //options: {
      //  separator: ';',
      //},
      dist: {
        src: ['<%= config.sass %>/temporal-admin.scss'],
        dest: '<%= config.sass %>/temporal-admin-concatenated.scss'
      }
    },
    uglify: {
      dist: {
        files: {
          '<%= config.js %>/temporal-admin.<%= config.jsVersionAdmin %>.min.js': ['<%= config.js %>/temporal-admin.js'],
          '<%= config.js %>/temporal.<%= config.jsVersion %>.min.js': ['<%= config.js %>/temporal.js'],
          '<%= config.js %>/temporal-ajax.<%= config.jsVersionAjax %>.min.js': ['<%= config.js %>/temporal-ajax.js']
        }
      }
    },
    watch: {
      stylesheets: {
        files: ['<%= config.sass %>/**/*.{sass,scss,css}'],
        tasks: ['concat', 'sass:prod']
      },
      js: {
        files: ['<%= config.js %>/temporal-admin.js',
                '<%= config.js %>/temporal.js',
                '<%= config.js %>/temporal-ajax.js'],
        tasks: ['uglify']
      }
    },
    copy: {
      cssadmin: {
        src: '<%= config.css %>/temporal-admin.<%= config.cssVersionAdmin %>.min.css',
        dest: '<%= config.css %>/temporal-admin.min.css',
      },
      css: {
        src: '<%= config.css %>/temporal.<%= config.cssVersion %>.min.css',
        dest: '<%= config.css %>/temporal.min.css',
      },
      jsadmin: {
        src: '<%= config.js %>/temporal-admin.<%= config.jsVersionAdmin %>.min.js',
        dest: '<%= config.js %>/temporal-admin.min.js',
      },
      jsajax: {
        src: '<%= config.js %>/temporal-ajax.<%= config.jsVersionAjax %>.min.js',
        dest: '<%= config.js %>/temporal-ajax.min.js',
      },
      js: {
        src: '<%= config.js %>/temporal.<%= config.jsVersion %>.min.js',
        dest: '<%= config.js %>/temporal.min.js',
      }
    },
    clean: {
      css: [ '<%= config.css %>/*.css',
             '!<%= config.sass %>/temporal-admin-concatenated.scss',
             '!<%= config.css %>/temporal.<%= config.cssVersion %>.min.css',
             '!<%= config.css %>/temporal-admin.<%= config.cssVersionAdmin %>.min.css' ],
      js: [ '<%= config.js %>/*.js',
            '!<%= config.js %>/temporal-admin.js',
            '!<%= config.js %>/temporal-admin.<%= config.jsVersionAdmin %>.min.js',
            '!<%= config.js %>/temporal-ajax.js',
            '!<%= config.js %>/temporal-ajax.<%= config.jsVersionAjax %>.min.js',
            '!<%= config.js %>/temporal.js',
            '!<%= config.js %>/temporal.<%= config.jsVersion %>.min.js' ]
    }
  });

  // Load tasks
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-sass');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-asset-version-json');

  // Default task(s)
  //grunt.registerTask('default', []);

  // Retrieves the latest file revs from the filerevs.json
  grunt.registerTask('update_revs', 'Updates the file revs from the JSON.', function() {
    var newFileRevs        = grunt.file.readJSON('filerevs.json');
    config.cssVersionAdmin = newFileRevs['assets/css/temporal-admin.min.css'];
    config.cssVersion      = newFileRevs['assets/css/temporal.min.css'];
    config.jsVersionAdmin  = newFileRevs['assets/js/temporal-admin.min.js'];
    config.jsVersionAjax   = newFileRevs['assets/js/temporal-ajax.min.js'];
    config.jsVersion       = newFileRevs['assets/js/temporal.min.js'];
  });

  // Default Task
  grunt.registerTask('default', [
    'watch'
  ]);

  // Task list for production build
  grunt.registerTask('build-production', [
    'copy',
    'asset_version_json',
    'update_revs',
    'sass:prod',
    'uglify',
    'clean'
  ]);

};