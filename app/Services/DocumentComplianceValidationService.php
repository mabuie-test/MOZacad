<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentComplianceValidationService
{
    public function validate(array $sections, array $blueprint, array $rules): array
    {
        $non = [];
        $normalizedTitles = array_map(fn($s) => $this->norm((string)($s['title'] ?? '')), $sections);
        $needsMethod = (bool) ($rules['structureRules']['requires_methodology'] ?? false);
        $required = ['introducao', 'conclusao'];
        if ($needsMethod) { $required[] = 'metodologia'; }
        if (!empty($rules['referenceRules']['style'])) { $required[] = 'referencias'; }

        foreach ($required as $req) {
            if (!$this->hasEquivalent($req, $normalizedTitles)) {
                $sev = $req === 'metodologia' ? 'major' : 'critical';
                $non[] = ['severity'=>$sev,'rule'=>'required_section_missing','message'=>'Secção obrigatória ausente: '.$req,'target'=>$req];
            }
        }

        return $this->dedup(['is_compliant' => count(array_filter($non, fn($i)=>$i['severity']==='critical'))===0, 'summary' => $this->summary($non), 'non_conformities' => $non]);
    }

    private function dedup(array $v): array { $seen=[];$out=[];foreach($v['non_conformities'] as $n){$k=($n['severity']??'').($n['rule']??'').$this->norm((string)($n['target']??'')).$this->norm((string)($n['message']??''));if(isset($seen[$k]))continue;$seen[$k]=1;$out[]=$n;} $v['non_conformities']=$out;$v['summary']=$this->summary($out);$v['is_compliant']=(($v['summary']['critical']??0)===0);return $v; }
    private function summary(array $non): array { $s=['critical'=>0,'major'=>0,'minor'=>0,'warning'=>0]; foreach($non as $n){$sev=$n['severity']??'minor'; if(isset($s[$sev]))$s[$sev]++;} return $s; }
    private function hasEquivalent(string $needle, array $titles): bool { foreach($titles as $t){ if(in_array($t,$this->equivalents($needle),true)) return true;} return false; }
    private function equivalents(string $key): array { return match($key){'conclusao'=>['conclusao','consideracoes finais','notas conclusivas'],'referencias'=>['referencias','referencias bibliograficas','bibliografia','obras consultadas'],'introducao'=>['introducao','apresentacao','contextualizacao inicial'],'metodologia'=>['metodologia','procedimentos metodologicos','percurso metodologico','nota metodologica'],default=>[$key]}; }
    private function norm(string $v): string { $v=mb_strtolower(trim($v)); $v=preg_replace('/^[0-9\.\-\)\s]+/u','',$v); $v=strtr($v,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']); $v=preg_replace('/[^a-z0-9\s]/u',' ',$v); return trim(preg_replace('/\s+/',' ',$v)); }
}
