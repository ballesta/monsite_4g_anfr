git filter-branch --force 															\
                  --index-filter 													\
                    'git rm --cached  -r --ignore-unmatch base_donnees/donnees monsite4g_anfr.sql' \
                  --prune-empty 													\
                  --tag-name-filter cat 											\
                  
