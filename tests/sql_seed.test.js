/** Verifies that optional demo-only PostgreSQL seed matches the original six tested levels. */
const fs=require('fs'),path=require('path');
const levels=require('./fixtures/sliding_levels.json');
const sql=fs.readFileSync(path.join(__dirname,'../database/postgres_seed_sliding.sql'),'utf8');
const schema=fs.readFileSync(path.join(__dirname,'../database/postgres_schema.sql'),'utf8');
let passed=0,failed=0;
const check=(what,ok)=>{if(ok)passed++;else{failed++;console.log('FAIL: '+what)}};
const escape=s=>"'"+String(s).replace(/'/g,"''")+"'";
check('demo seed does not force legacy IDs', !/INSERT INTO public.levels \(level_id/i.test(sql));
check('complete import schema has no demo inserts', !/INSERT INTO public.levels/i.test(schema));
check('schema has users and progress', /CREATE TABLE IF NOT EXISTS public.users/.test(schema)&&/CREATE TABLE IF NOT EXISTS public.user_progress/.test(schema));
for(const L of levels){
 const contents=[escape(L.name),escape(L.hint),escape(L.difficulty.toLowerCase()),escape(L.rows.join('\n')),String(L.best)];
 check('level '+L.id+' original layout, hint, difficulty, par',contents.every(c=>sql.includes(c)));
}
check('all six levels are optional seeds', (sql.match(/INSERT INTO public.levels/g)||[]).length===6);
console.log((failed?'FAILURES':'ALL PASSED')+': '+passed+' checks passed, '+failed+' failed');
process.exit(failed?1:0);
