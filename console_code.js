(function() {                                                               
      // Extract page number from URL                                         
      const urlParams = new URLSearchParams(window.location.search);          
      const pageNum = urlParams.get('page') || '1';                           
                                                                              
      function extractAndDownload() {                                         
          const table =                                                       
  document.querySelector('table.Polaris-DataTable__Table');                   
          if (!table) {                                                       
              console.error('Table not found');                               
              return;                                                         
          }                                                                   
                                                                              
          const rows = Array.from(table.querySelectorAll('tbody tr'));        
          const data = rows.map(row => {                                      
              const cells = Array.from(row.querySelectorAll('td, th'));       
              return {                                                        
                  date: cells[0]?.textContent.trim() || '',                   
                  store_name: cells[1]?.querySelector('a')?.textContent.trim()
   || '',                                                                     
                  store_url:                                                  
  cells[1]?.querySelector('a')?.getAttribute('href') || '',                   
                  event: cells[2]?.textContent.trim() || '',                  
                  event_details: cells[3]?.textContent.trim() || ''           
              };                                                              
          });                                                                 
                                                                              
          console.log(`Page ${pageNum}: Extracted ${data.length} rows`);      
                                                                              
          // Download with page number                                        
          const blob = new Blob([JSON.stringify(data, null, 2)], { type:      
  'application/json' });                                                      
          const url = URL.createObjectURL(blob);                              
          const a = document.createElement('a');                              
          a.href = url;                                                       
          a.download = `shopify-history-page-${pageNum}.json`;                
          document.body.appendChild(a);                                       
          a.click();                                                          
          document.body.removeChild(a);                                       
          URL.revokeObjectURL(url);                                           
                                                                              
          // Click Next button after delay                                    
          const nextBtn =                                                     
  document.querySelector('#nextURL:not(.Polaris-Button--disabled)');          
          if (nextBtn) {                                                      
              console.log(`Waiting 3 seconds before clicking Next...`);       
              setTimeout(() => {                                              
                  nextBtn.click();                                            
                  // Re-run script after page loads                           
                  setTimeout(() => location.reload(), 2000);                  
              }, 3000);                                                       
          } else {                                                            
              console.log('No more pages - done!');                           
          }                                                                   
      }                                                                       
                                                                              
      extractAndDownload();                                                   
  })();   