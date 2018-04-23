module.exports = function(grunt) {

	grunt.initConfig({

		pkg: grunt.file.readJSON('package.json'),

		sass: {
			options: {
				outputStyle: 'compressed'
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
				tasks: ['uglify:main','wp_readme_to_markdown']
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
			transifex: 'tx pull -a',

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

		wp_readme_to_markdown: {
			your_target: {
				files: {
					'readme.md': 'readme.txt'
				},
			},
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
						pot.headers['language-team'] = 'GravityView <support@gravityview.co>';
						pot.headers['last-translator'] = 'GravityView <support@gravityview.co>';
						pot.headers['report-msgid-bugs-to'] = 'https://gravityview.co/support/';

						var translation,
							excluded_meta = [
								'GravityView - A-Z Filters Extension',
								'Filter your entries by letters of the alphabet.',
								'https://gravityview.co',
								'GravityView',
								'https://gravityview.co/extensions/a-z-filter/'
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

	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
	grunt.loadNpmTasks('grunt-potomo');
	grunt.loadNpmTasks('grunt-exec');
	grunt.loadNpmTasks('grunt-wp-i18n');

	grunt.registerTask( 'default', [ 'sass', 'uglify', 'exec:transifex','potomo', 'watch'] );

	// Translation stuff
	grunt.registerTask( 'translate', [ 'exec:transifex', 'potomo', 'addtextdomain', 'makepot' ] );

};
