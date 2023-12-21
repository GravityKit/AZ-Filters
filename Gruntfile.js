module.exports = function(grunt) {
	const sass = require( 'node-sass' );

	require('load-grunt-tasks')(grunt);

	grunt.initConfig({

		pkg: grunt.file.readJSON('package.json'),

		sass: {
			options: {
				implementation: sass,
				outputStyle: 'compressed',
				sourceMap: false
			},
			dist: {
				files: [{
		          expand: true,
		          cwd: 'assets/css/scss',
		          src: ['*.scss'],
		          dest: 'assets/css',
		          ext: '.css'
		      }]
			}
		},

		uglify: {
			options: { mangle: false },
			main: {
				files: [{
		          expand: true,
		          cwd: 'assets/js',
		          src: ['**/*.js','!**/*.min.js'],
		          dest: 'assets/js',
		          ext: '.min.js'
		      }]
			}
		},

		watch: {
			main: {
				files: ['assets/js/*.js','!assets/js/*.min.js','readme.txt'],
				tasks: ['uglify:main']
			},
			scss: {
				files: ['assets/css/scss/*.scss'],
				tasks: ['sass:dist']
			}
		},

		dirs: {
			lang: 'languages'
		},

		// Convert the .po files to .mo files
		potomo: {
			dist: {
				options: {
					poDel: false
				},
				files: [{
					expand: true,
					cwd: '<%= dirs.lang %>',
					src: ['*.po'],
					dest: '<%= dirs.lang %>',
					ext: '.mo',
					nonull: true
				}]
			}
		},

		// Pull in the latest translations
		exec: {
			// Create a ZIP file
			// Create a ZIP file
			zip: {
				cmd: function( version = '' ) {

					var filename = ( version === '' ) ? 'gravityview-az-filters' : 'gravityview-az-filters-' + version;

					// First, create the full archive
					var command = 'git-archive-all gravityview-az-filters.zip &&';

					command += 'unzip -o gravityview-az-filters.zip &&';

					command += 'zip -r ../' + filename + '.zip "gravityview-az-filters" &&';

					command += 'rm -rf "gravityview-az-filters/" && rm -f "gravityview-az-filters.zip"';

					return command;
				}
			}
		},

		// Build translations without POEdit
		makepot: {
			target: {
				options: {
					mainFile: 'gravityview-az-filters.php',
					type: 'wp-plugin',
					domainPath: '/languages',
					updateTimestamp: false,
					exclude: ['node_modules/.*', 'assets/.*', 'tmp/.*', 'vendor/.*', 'includes/lib/xml-parsers/.*', 'includes/lib/jquery-cookie/.*' ],
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true
					},
					processPot: function( pot, options ) {
						pot.headers['language'] = 'en_US';
						pot.headers['language-team'] = 'GravityKit <support@gravitykit.com>';
						pot.headers['last-translator'] = 'GravityKit <support@gravitykit.com>';
						pot.headers['report-msgid-bugs-to'] = 'https://www.gravitykit.com/support/';

						var translation,
							excluded_meta = [
								'GravityView - A-Z Filters Extension',
								'Filter your entries by letters of the alphabet.',
								'https://www.gravitykit.com',
								'GravityView',
								'https://www.gravitykit.com/extensions/a-z-filter/'
							];

						for ( translation in pot.translations[''] ) {
							if ( 'undefined' !== typeof pot.translations[''][ translation ].comments.extracted ) {
								if ( excluded_meta.indexOf( pot.translations[''][ translation ].msgid ) >= 0 ) {
									console.log( 'Excluded meta: ' + pot.translations[''][ translation ].msgid );
									delete pot.translations[''][ translation ];
								}
							}
						}

						return pot;
					}
				}
			}
		},

		// Add textdomain to all strings, and modify existing textdomains in included packages.
		addtextdomain: {
			options: {
				textdomain: 'gravityview-az-filters',    // Project text domain.
				updateDomains: [ 'gravityview', 'gravity-view', 'gravityforms', 'edd_sl', 'edd', 'easy-digital-downloads' ]  // List of text domains to replace.
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
		}
	});

	grunt.registerTask( 'default', [ 'sass', 'uglify', 'translate'] );

	// Translation stuff
	grunt.registerTask( 'translate', [ 'potomo', 'addtextdomain', 'makepot' ] );

};
