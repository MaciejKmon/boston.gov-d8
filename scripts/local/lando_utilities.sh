#!/bin/bash

# Define colors
Black='\033[0;30m'
DarkGray='\033[1;30m'
Red='\033[1;31m'
LightRed='\033[0;31m'
Green='\033[0;32m'
LightGreen='\033[1;32m'
BrownOrange='\033[0;33m'
Yellow='\033[1;33m'
Blue='\033[0;34m'
LightBlue='\033[1;34m'
Purple='\033[0;35m'
LightPurple='\033[1;35m'
Cyan='\033[0;36m'
LightCyan='\033[1;36m'
LightGray='\033[0;37m'
White='\033[1;37m'
NC='\033[0m'

# basic parse of a yml file into a series of variables.
function parse_yaml() {
   local prefix=${2}
   local s='[[:space:]]*' w='[a-zA-Z0-9_]*' fs=$(echo @|tr @ '\034')
   sed -ne "s|^\($s\)\($w\)$s:$s\"\(.*\)\"$s\$|\1$fs\2$fs\3|p" \
        -e "s|^\($s\)\($w\)$s:$s\(.*\)$s\$|\1$fs\2$fs\3|p"  ${1} |
   awk -F$fs '{
      indent = length($1)/2;
      vname[indent] = $2;
      for (i in vname) {if (i > indent) {delete vname[i]}}
      if (length($3) > 0) {
         vn=""; for (i=0; i<indent; i++) {vn=(vn)(vname[i])("_")}
         printf("%s%s%s=\"%s\"\n", "'${prefix}'",vn, $2, $3);
      }
   }'
}

# Wrapper to load the .lando.yml file
function load_lando_yml() {
    eval $(parse_yaml "${LANDO_MOUNT}/.lando.yml" "lando_")
}

function printout () {

  if [ "${1}" == "ERROR" ]; then
    col1=${Red}
    col2=${LightRed}
  elif [ "${1}" == "WARNING" ]; then
    col1=${Yellow}
    col2=${BrownOrange}
  elif [ "${1}" == "INFO" ] || [ "${1}" == "STATUS" ]; then
    col1=${LightBlue}
    col2=${Cyan}
  else
    col1=${LightGreen}
    col2=${Green}
  fi

  if [[ -n ${1} ]]; then
    printf "$col1[${1}] "
  fi
  if [[ -n ${2} ]]; then
      printf "$col2${2}$NC "
  fi
  if [[ -n ${3} ]]; then
    printf "$LightGray- ${3}$NC"
  fi
  printf "\n"
}

function clone_private_repo() {
  printout "INFO" "Clone private repo and merge with main repo."

  # Assign a temporary folder.
  if [[ -n "${git_private_repo_local_dir}" ]]; then
    git_private_repo_local_dir="${LANDO_MOUNT}/tmprepo"
  fi

  # Clone the repo and merge
  git clone git@github.com:CityOfBoston/boston.gov-d8-private.git ${git_private_repo_local_dir} -q --depth 1 &&
    rm -rf ${git_private_repo_local_dir}/.git &&
    find ${git_private_repo_local_dir}/. -iname '*..gitignore' -exec rename 's/\.\.gitignore/\.gitignore/' '{}' \; &&
    rsync -aE "${git_private_repo_local_dir}/" "${LANDO_MOUNT}/" --exclude=*.md &&
    rm -rf ${git_private_repo_local_dir} &&
    printout "SUCCESS" "Private repo merged."

  if [[ $? -ne 0 ]]; then
    printout "ERROR" "Failed to clone/merge private repo."
    exit 1
  fi
}

function build_settings() {

    printout "INFO" "Will update and implement settings files."

    if [[ -z "${project_docroot}}" ]]; then
        # Read in config and variables.
        eval $(parse_yaml "${LANDO_MOUNT}/scripts/local/.config.yml" "")
        eval $(parse_yaml "${LANDO_MOUNT}/.lando.yml" "lando_")
    fi

    # Set local variables
    settings_path="${project_docroot}/sites/${drupal_multisite_name}"
    settings_file="${settings_path}/settings.php"
    default_settings_file="${settings_path}/default.settings.php"
    services_file="${settings_path}/services.yml"
    default_services_file="${settings_path}/default.services.yml"
    local_settings_file="${settings_path}/settings/settings.local.php"
    default_local_settings_file="${settings_path}/settings/default.local.settings.php"
    private_settings_file="${settings_path}/settings/${git_private_repo_settings_file}"

    # Setup hooks from inside settings.php
    if [[ ! -e ${settings_file} ]]; then
        # Copy default file.
        cp default_settings_file settings_file
    fi

    # Setup the local.settings.php file
    if [[ ! -e ${local_settings_file} ]]; then
        # Copy default file.
        cp default_local_settings_file local_settings_file
    fi
    echo -e "/*\n * Content added by COB.\n */\n" >> ${local_settings_file}
    if [[ -n "${private_settings_file}" ]]; then
        # If a private settings file is defined, then make a reference to it from the local.settings.php file.
        echo -e "\n// Adds a directive to include contents of settings file in repo.\n" >> ${local_settings_file}
        echo -e "if (file_exists(DRUPAL_ROOT . \"/docroot/${git_private_repo_settings_file}\")) {\n" >> ${local_settings_file}
        echo -e "  include DRUPAL_ROOT . \"/docroot/${git_private_repo_settings_file}\";\n" >> ${local_settings_file}
        echo -e "}\n\n" >> ${local_settings_file}
    fi
    # Add in config sync directory from yml.
    echo -e "$config_directories['sync'] = '${build.config.sync}';\n" >> ${local_settings_file}
    echo -e "$settings['install_profile'] = '${project.profile.name}';\n" >> ${local_settings_file}
    echo -e "/* End of additions. */\n" >> ${local_settings_file}

    # setup the private settings file
#    if [[ -n "${private_settings_file}" ]] && [[ -e ${private_settings_file} ]]; then
#        # There is a private settings file.
#    fi

    # Setup the serices.yml file
    if [[ ! -e ${services_file} ]]; then
        # Copy default file.
        cp default_services_file services_file
    fi

    # Remove un-needed settings files.
    rm -f "${default_settings_file}"
    rm -f "${default_local_settings_file}"
    rm -f "${default_services_file}"
    rm -f "${docroot}/sites/example.settings.local.php"
    rm -f "${docroot}/sites/example.sites.php"

    printout "SUCCESS" "Settings files written/updated."

}