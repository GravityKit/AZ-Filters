module.exports = function ( grunt ) {
	const sass = require( 'node-sass' );

	require( 'load-grunt-tasks' )( grunt );

	grunt.initConfig( {
		sass: {
			options: {
				implementation: sass,
				outputStyle: 'compressed',
				sourceMap: false
			},
			dist: {
				files: [ {
					expand: true,
					cwd: 'assets/css/scss',
					src: [ '*.scss' ],
					dest: 'assets/css',
					ext: '.css'
				} ]
			}
		},

		uglify: {
			options: { mangle: false },
			main: {
				files: [ {
					expand: true,
					cwd: 'assets/js',
					src: [ '**/*.js', '!**/*.min.js' ],
					dest: 'assets/js',
					ext: '.min.js'
				} ]
			}
		},

		watch: {
			main: {
				files: [ 'assets/js/*.js', '!assets/js/*.min.js', 'readme.txt' ],
				tasks: [ 'uglify:main' ]
			},
			scss: {
				files: [ 'assets/css/scss/*.scss' ],
				tasks: [ 'sass:dist' ]
			}
		},

		// Add text domain to all strings, and modify existing text domains in included packages.
		addtextdomain: {
			options: {
				textdomain: 'gravityview-az-filters',    // Project text domain.
				updateDomains: [ 'gravityview' ]  // List of text domains to replace.
			},
			target: {
				files: {
					src: [
						'*.php',
						'**/*.php',
						'!node_modules/**',
						'!tests/**',
						'!tmp/**',
						'!vendor/**'
					]
				}
			}
		},

		exec: {
			// Generate POT file.
			makepot: {
				cmd: function () {
					var fileComments = [
						'Copyright (C) ' + new Date().getFullYear() + ' GravityKit',
						'This file is distributed under the GPLv2 or later',
					];

					var headers = {
						'Last-Translator': 'GravityKit <support@gravitykit.com>',
						'Language-Team': 'GravityKit <support@gravitykit.com>',
						'Language': 'en_US',
						'Plural-Forms': 'nplurals=2; plural=(n != 1);',
						'Report-Msgid-Bugs-To': 'https://www.gravitykit.com/support',
					};

					var command = 'wp i18n make-pot --exclude=build . translations.pot';

					command += ' --file-comment="' + fileComments.join( '\n' ) + '"';

					command += ' --headers=\'' + JSON.stringify( headers ) + '\'';

					return command;
				}
			},
		}
	} );

	grunt.registerTask( 'default', [ 'sass', 'uglify' ] );

	// Translation stuff.
	grunt.registerTask( 'translate', [ 'addtextdomain', 'exec:makepot' ] );

};
