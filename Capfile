load 'deploy'
# Uncomment if you are using Rails' asset pipeline
    # load 'deploy/assets'
Dir['vendor/gems/*/recipes/*.rb','vendor/plugins/*/recipes/*.rb'].each { |plugin| load(plugin) }
load 'config/deploy' # remove this line to skip loading any of the default tasks
set :keep_releases, 50
after "deploy:restart", "deploy:cleanup"

on :start, "options:process"
namespace :options do

  desc "Show how to read in command line options"
  task :process do
    if( ENV['branch'] )
      p "branch is #{ENV['branch']}"
      set :branch , ENV["branch"]
    else
      set :branch , "master"
    end
  end

end

task :admin do
 @filter = "padm"
end
