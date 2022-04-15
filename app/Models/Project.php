<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\Deploy\SSH\SshKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'server_id', 'server_path', 'git_repository', 'git_branch', 'git_ssh_key', 'type', 'shared_directories', 'shared_files'];

    const TYPE_PRODUCTION = 'production';
    const TYPE_STAGING = 'staging';
    const TYPE_DEVELOPMENT = 'development';
    const TYPE_QA = 'qa';
    

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function deployments()
    {
        return $this->hasMany(Deployment::class);
    }

    public function scopeWithLastDeployed(Builder $builder)
    {
        return $builder->addSelect([
            'last_deployed' => Deployment::query()
                ->select('updated_at')
                ->whereColumn('project_id', 'projects.id')
                ->latest()
                ->limit(1)
        ]);
    }

    public function appendLog($log)
    {
        $deployment = $this->deployments()->active()->first();
        if ( !$deployment ){
            return;
        }
        $deployment->refresh();
        $deployment->logs .= $log."\n";
        $deployment->save();
    }

    public function addFormatedLog($log)
    {
        // $fLog = "***************************************\n";
        $fLog = $log."\n"; 
        $fLog .= "***************************************\n";
        return $this->appendLog($fLog); 
    }


    public function createOrFindActiveDeployment()
    {
        $deployment = $this->getActiveDeployment();
        if ( !$deployment ){
            return $this->createNewDeployment();
        }

        return $deployment;
    }

    public function createNewDeployment()
    {
        return $this->deployments()->create([
            'is_active' => true,
            'runtime' => 0,
            'status' => Deployment::STATUS_RUNNING,
        ]);
    }

    public function getActiveDeployment()
    {
        return $this->deployments()->active()->first();
    }

    public function closeActiveDeployment($status)
    {
        $deployment = $this->getActiveDeployment();

        if ( !$deployment ){
            return;
        }

        return $deployment->update([
            'is_active' => false,
            'runtime' => now()->diffInMilliseconds($deployment->created_at),
            'status' => $status,
        ]);
    }

    public function generateSshKey($save = false)
    {
        $this->git_ssh_key = $this->server_id ? SshKey::generate($this, true) : null;

        if($save) $this->save();
    }

    protected function getGitPublicKeyAttribute()
    {
        return $this->server_id ? SshKey::publicKey($this) : null;
    }

    public function isDeployable()
    {
        $required_data = [
            'server_id',
            'git_repository',
            'git_branch',
            'server_path',
        ];
        
        return collect($this->only($required_data))->filter(fn($value) => !empty($value))->count() === count($required_data);
    }

    protected static function boot()
    {
        parent::boot();
        
        self::deleting(function(Project $project){
            // $project->deployments->each(function($deployment){
            //     $deployment->delete();
            // });
            $project->deployments()->delete();
        });
    }
}
